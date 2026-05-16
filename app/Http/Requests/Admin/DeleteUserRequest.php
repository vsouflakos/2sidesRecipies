<?php

namespace App\Http\Requests\Admin;

use App\Concerns\AdminUserGuards;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class DeleteUserRequest extends FormRequest
{
    use AdminUserGuards;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Guard checks fire before the permission check so that the last-admin
     * guard returns 422 even when the requesting user no longer holds
     * manage-users (e.g. after being demoted in the same session).
     */
    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        if ($this->isActingOnSelf($target)) {
            throw new HttpResponseException(
                response()->json([
                    'message' => __('app.admin.guard_self_delete'),
                    'errors' => ['user' => [__('app.admin.guard_self_delete')]],
                ], 422)
            );
        }

        if ($this->isLastAdmin($target)) {
            throw new HttpResponseException(
                response()->json([
                    'message' => __('app.admin.guard_last_admin_delete'),
                    'errors' => ['user' => [__('app.admin.guard_last_admin_delete')]],
                ], 422)
            );
        }

        return (bool) $this->user()?->can('manage-users');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance with additional after-validation checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var User $target */
            $target = $this->route('user');

            if ($this->isActingOnSelf($target)) {
                $v->errors()->add('user', __('app.admin.guard_self_delete'));
            }

            if ($this->isLastAdmin($target)) {
                $v->errors()->add('user', __('app.admin.guard_last_admin_delete'));
            }
        });
    }
}
