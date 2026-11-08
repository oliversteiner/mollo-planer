<?php

namespace App\GraphQL\Validators\Mutation;

use App\Services\UserAvatar;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Nuwave\Lighthouse\Validation\Validator;

/**
 * updateMyAvatar: JPG or PNG (content and file extension), at most 5 MB
 * and not larger than UserAvatar::MAX_DIMENSION pixels per side.
 */
class UpdateMyAvatarValidator extends Validator
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                File::types(UserAvatar::EXTENSIONS)
                    ->extensions(UserAvatar::EXTENSIONS)
                    ->max(UserAvatar::MAX_KILOBYTES),
                Rule::dimensions()
                    ->maxWidth(UserAvatar::MAX_DIMENSION)
                    ->maxHeight(UserAvatar::MAX_DIMENSION),
            ],
        ];
    }
}
