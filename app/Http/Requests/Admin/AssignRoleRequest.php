<?php

namespace App\Http\Requests\Admin;

use App\Concerns\AdminUserGuards;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignRoleRequest extends FormRequest
{
    use AdminUserGuards;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Guard checks are evaluated before the permission check so that the
     * last-admin guard returns 422 even when the requesting user no longer
     * holds manage-users (e.g. after being demoted in the same session).
     */
    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        if ($this->isActingOnSelf($target)) {
            throw new HttpResponseException(
                response()->json([
                    'message' => __('app.admin.guard_self_role'),
                    'errors' => ['role' => [__('app.admin.guard_self_role')]],
                ], 422)
            );
        }

        if ($this->isLastAdmin($target) && $this->input('role') !== 'Admin') {
            throw new HttpResponseException(
                response()->json([
                    'message' => __('app.admin.guard_last_admin_role'),
                    'errors' => ['role' => [__('app.admin.guard_last_admin_role')]],
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
        return [
            'role' => ['required', 'string', Rule::in(['User', 'Moderator', 'Admin'])],
        ];
    }

    /**
     * Configure the validator instance with additional after-validation checks.
     *
     * Guards are also checked here for requests that pass the permission check,
     * ensuring they fire even when roles change mid-session.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var User $target */
            $target = $this->route('user');

            if ($this->isActingOnSelf($target)) {
                $v->errors()->add('role', __('app.admin.guard_self_role'));
            }

            if ($this->isLastAdmin($target) && $this->input('role') !== 'Admin') {
                $v->errors()->add('role', __('app.admin.guard_last_admin_role'));
            }
        });
    }
}
