<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class AttendanceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'note' => ['required', 'string', 'max:500'],

            // 休憩データは配列として受け取る
            'breaks.*.start' => ['nullable', 'date_format:H:i'],
            'breaks.*.end' => ['nullable', 'date_format:H:i', 'after:breaks.*.start'],
        ];
    }

    public function messages()
    {
        return [
            'start_time.required' => '出勤時刻は必須です',
            'start_time.date_format' => '出勤時刻は正しい時刻形式で入力してください',
            'end_time.date_format' => '退勤時刻は正しい時刻形式で入力してください',
            'end_time.after' => '出勤時間が不適切な値です',
            'note.required' => '備考を入力してください',
            'note.max' => '備考は500文字以内で入力してください',

            'breaks.*.start.date_format' => '休憩開始時刻は正しい時刻形式で入力してください',
            'breaks.*.end.date_format' => '休憩終了時刻は正しい時刻形式で入力してください',
            'breaks.*.end.after' => '休憩時間が不適切な値です',
            'breaks_validation' => '休憩時間もしくは退勤時間が不適切な値です',
        ];
    }

    /**
     * カスタムバリデーション処理
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $end_time = $this->input('end_time');
            $breaks = $this->input('breaks', []);

            // 退勤時間が指定されている場合のみチェック
            if ($end_time && is_array($breaks)) {
                foreach ($breaks as $index => $break) {
                    // 休憩が開始時刻と終了時刻の両方が指定されている場合
                    if (!empty($break['start']) && !empty($break['end'])) {
                        // 休憩終了時刻が退勤時刻を超えていないかチェック
                        if ($break['end'] > $end_time) {
                            $validator->errors()->add(
                                "breaks.{$index}.end",
                                '休憩時間もしくは退勤時間が不適切な値です'
                            );
                        }
                    }
                }
            }
        });

        return $this;
    }
}
