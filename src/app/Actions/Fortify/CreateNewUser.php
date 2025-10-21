<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => ['required','string','min:8'],
            'password_confirmation'=>['required','same:password'],
        ];

        $messages = [
            'name.required' => 'お名前を入力してください',
            'name.string' => 'お名前は文字で入力してください',
            'name.max' => 'お名前は255字以下で入力してください',
            'email.required' => 'メールアドレスを入力してください',
            'email.string' => 'メールアドレスは文字で入力してください',
            'email.max' => 'メールアドレスは255字以下で入力してください',
            'email.unique' => 'こちらのメールアドレスはすでに登録されています',
            'password.required' => 'パスワードを入力してください',
            'password.string' => 'パスワードは文字列で入力してください',
            'password.min' => 'パスワードは８文字以上で入力してください',
            'password_confirmation.required' => '確認用パスワードを入力してください',
            'password_confirmation.same' =>'パスワードと一致しません'

        ];

        Validator::make($input,$rules,$messages)->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
