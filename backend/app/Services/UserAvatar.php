<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Avatar images of users.
 *
 * Uploads (JPG or PNG, at most 5 MB) are turned upright (EXIF), cropped to
 * a centred square of 500 x 500 pixels and stored as WebP under a random
 * name on the "public" disk. Re-encoding drops all metadata, such as the
 * GPS position of a phone photo.
 */
class UserAvatar
{
    public const DISK = 'public';

    public const DIRECTORY = 'avatars';

    public const SIZE = 500;

    public const MAX_KILOBYTES = 5 * 1024;

    /**
     * Larger images are rejected before decoding: a small, well-compressed
     * PNG can otherwise expand to more memory than PHP has.
     */
    public const MAX_DIMENSION = 6000;

    /**
     * @var list<string>
     */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /**
     * Replace the user's avatar. Call inside a transaction: the old file is
     * deleted only after the commit, the new one is removed again if the
     * transaction is rolled back.
     */
    public function replace(User $user, UploadedFile $upload): User
    {
        $path = Image::fromUpload($upload)
            ->orient()
            ->cover(self::SIZE, self::SIZE)
            ->toWebp()
            ->quality(85)
            ->storePublicly(self::DIRECTORY, self::DISK);

        if ($path === false) {
            throw new RuntimeException('The avatar could not be stored.');
        }

        DB::afterRollBack(fn () => Storage::disk(self::DISK)->delete($path));
        $this->setPath($user, $path);

        return $user;
    }

    /**
     * Remove the user's avatar. Call inside a transaction.
     */
    public function remove(User $user): User
    {
        $this->setPath($user, null);

        return $user;
    }

    /**
     * Delete a stored avatar file once the current transaction is committed.
     */
    public function deleteFileAfterCommit(?string $path): void
    {
        if ($path === null) {
            return;
        }

        DB::afterCommit(fn () => Storage::disk(self::DISK)->delete($path));
    }

    private function setPath(User $user, ?string $path): void
    {
        $oldPath = $user->avatar_path;

        $user->avatar_path = $path;
        $user->save();

        if ($oldPath !== $path) {
            $this->deleteFileAfterCommit($oldPath);
        }
    }
}
