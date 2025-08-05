<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->hasRole('gngnunet_office_admin'|| 'gngnunet_office_coordinator');
    }

    public function rules(): array
    {
        $studentId = $this->route('student')->id ?? null;

        return [
            'name' => 'required|string|max:255',
            'christian_name' => 'nullable|string|max:255',
            'sex' => 'required|in:Male,Female',
            'age' => 'required|integer|min:1|max:150',
            'phone_number' => 'required|string|max:20',
            'program_type_id' => 'required|exists:program_types,id',
            'email_address' => 'nullable|email',
            'telegram_user_name' => 'nullable|string|max:255',
            'section_id' => 'required|exists:sections,id',
            'round' => 'nullable|string|max:255',
            'educational_level' => 'nullable|string|max:255',
            'student_id' => 'required|string|unique:students,student_id,' . $studentId,
            'type' => 'nullable|string',
            'subcity' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'special_place' => 'nullable|string|max:255',
            'house_number' => 'nullable|string|max:255',
            'contacts' => 'array|required',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.phone_number' => 'required|string|max:20',
            'contacts.*.type' => 'nullable|string|max:100',
        ];
    }
}
