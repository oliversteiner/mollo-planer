<?php

namespace Tests\Feature\GraphQL;

use App\Services\UserAvatar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

class AvatarTest extends GraphQLTestCase
{
    private const UPLOAD = 'mutation ($file: Upload!) { updateMyAvatar(avatar: $file) { avatarUrl } }';

    public function test_upload_is_cropped_to_a_500_pixel_square_webp(): void
    {
        Storage::fake(UserAvatar::DISK);

        $url = $this->upload('member', UploadedFile::fake()->image('portrait.jpg', 1200, 800))
            ->assertJsonMissingPath('errors')
            ->json('data.updateMyAvatar.avatarUrl');

        $path = $this->users['member']->fresh()->avatar_path;
        $this->assertStringStartsWith(UserAvatar::DIRECTORY.'/', $path);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame(Storage::disk(UserAvatar::DISK)->url($path), $url);

        [$width, $height, $type] = getimagesizefromstring(Storage::disk(UserAvatar::DISK)->get($path));
        $this->assertSame([500, 500, IMAGETYPE_WEBP], [$width, $height, $type]);
    }

    public function test_a_new_avatar_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake(UserAvatar::DISK);
        $this->upload('member', UploadedFile::fake()->image('first.jpeg', 600, 600))->assertJsonMissingPath('errors');
        $oldPath = $this->users['member']->fresh()->avatar_path;

        $this->upload('member', UploadedFile::fake()->image('second.png', 300, 900))->assertJsonMissingPath('errors');

        $newPath = $this->users['member']->fresh()->avatar_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk(UserAvatar::DISK)->assertMissing($oldPath);
        Storage::disk(UserAvatar::DISK)->assertExists($newPath);
    }

    /**
     * @param  \Closure(): UploadedFile  $file
     */
    #[DataProvider('rejectedUploads')]
    public function test_rejected_uploads_store_nothing(\Closure $file): void
    {
        Storage::fake(UserAvatar::DISK);

        $this->upload('member', $file())->assertGraphQLValidationKeys(['avatar']);

        $this->assertNull($this->users['member']->fresh()->avatar_path);
        $this->assertSame([], Storage::disk(UserAvatar::DISK)->allFiles());
    }

    /**
     * @return array<string, array{\Closure(): UploadedFile}>
     */
    public static function rejectedUploads(): array
    {
        return [
            'gif' => [fn () => UploadedFile::fake()->image('animated.gif', 100, 100)],
            'webp' => [fn () => UploadedFile::fake()->image('modern.webp', 100, 100)],
            'larger than 5 MB' => [fn () => UploadedFile::fake()->image('huge.jpg', 100, 100)->size(UserAvatar::MAX_KILOBYTES + 1)],
            'pdf named jpg' => [fn () => UploadedFile::fake()->create('fake.jpg', 10, 'application/pdf')],
            'png named txt' => [fn () => UploadedFile::fake()->image('picture.txt', 100, 100)],
            'too many pixels' => [fn () => UploadedFile::fake()->image('panorama.png', UserAvatar::MAX_DIMENSION + 1, 10)],
        ];
    }

    public function test_exactly_5_mb_is_accepted(): void
    {
        Storage::fake(UserAvatar::DISK);

        $this->upload('member', UploadedFile::fake()->image('big.jpg', 100, 100)->size(UserAvatar::MAX_KILOBYTES))
            ->assertJsonMissingPath('errors');
    }

    public function test_own_avatar_and_admins_may_remove_it_others_may_not(): void
    {
        Storage::fake(UserAvatar::DISK);
        $remove = 'mutation ($id: ID!) { deleteUserAvatar(id: $id) { avatarUrl } }';
        $member = ['id' => $this->users['member']->id];

        $this->upload('member', UploadedFile::fake()->image('a.jpg', 100, 100));
        $this->assertUnauthorized($this->as('owner')->gql($remove, $member));
        $this->as('member')->gql($remove, $member)->assertJsonPath('data.deleteUserAvatar.avatarUrl', null);

        $this->upload('member', UploadedFile::fake()->image('b.jpg', 100, 100));
        $path = $this->users['member']->fresh()->avatar_path;
        $this->as('admin')->gql($remove, $member)->assertJsonPath('data.deleteUserAvatar.avatarUrl', null);

        $this->assertNull($this->users['member']->fresh()->avatar_path);
        Storage::disk(UserAvatar::DISK)->assertMissing($path);
    }

    public function test_deleting_a_user_deletes_the_avatar_file(): void
    {
        Storage::fake(UserAvatar::DISK);
        $this->upload('stranger', UploadedFile::fake()->image('a.jpg', 100, 100));
        $path = $this->users['stranger']->fresh()->avatar_path;

        $this->as('admin')->gql('mutation ($id: ID!) { deleteUser(id: $id) { id } }', ['id' => $this->users['stranger']->id])
            ->assertJsonMissingPath('errors');

        Storage::disk(UserAvatar::DISK)->assertMissing($path);
    }

    private function upload(string $actor, UploadedFile $file): TestResponse
    {
        $this->as($actor);

        return $this->multipartGraphQL(
            ['query' => self::UPLOAD, 'variables' => ['file' => null]],
            ['0' => ['variables.file']],
            ['0' => $file],
            ['Referer' => 'http://localhost'],
        );
    }
}
