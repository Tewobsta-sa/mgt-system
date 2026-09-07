<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function address()
    {
        return $this->hasOne(Address::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function mezmurExamResults()
    {
        return $this->hasMany(MezmurExamResult::class);
    }

    public function nominator()
    {
        return $this->belongsTo(User::class, 'nominated_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function endorser()
    {
        return $this->belongsTo(User::class, 'endorsed_by');
    }

    public function flagger()
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function targetSection()
    {
        return $this->belongsTo(Section::class, 'target_section_id');
    }

    public function scopeNotFlagged($query)
    {
        return $query->where('is_flagged', false);
    }

    protected $appends = [
        'picture_url',
        'birth_certificates_urls',
        'educational_certificates_urls',
    ];

    public function getPictureUrlAttribute()
    {
        if (!$this->picture) return null;
        if (filter_var($this->picture, FILTER_VALIDATE_URL)) return $this->picture;
        // Serve via /api/media so the SPA (different origin) can use photos in canvas/PDF
        return url('api/media/' . ltrim($this->picture, '/'));
    }

    public function getBirthCertificatesUrlsAttribute()
    {
        if (empty($this->birth_certificates)) return [];
        return array_map(function ($path) {
            if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
            return url('storage/' . ltrim($path, '/'));
        }, (array) $this->birth_certificates);
    }

    public function getEducationalCertificatesUrlsAttribute()
    {
        if (empty($this->educational_certificates)) return [];
        return array_map(function ($path) {
            if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
            return url('storage/' . ltrim($path, '/'));
        }, (array) $this->educational_certificates);
    }

    protected $fillable = [
        'student_id',
        'name',
        'christian_name',
        'sex',
        'birth_date',
        'age',
        'phone_number',
        'email_address',
        'telegram_user_name',
        'section_id',
        'round',
        'educational_level',
        'grade_level',
        'occupation_type',
        'current_school',
        'current_office',
        'family_guardian_name',
        'family_guardian_phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'picture',
        'birth_certificates',
        'educational_certificates',
        'classification',
        'is_night',
        'address_id',
        'status',        
        'is_flagged',
        'flag_reason',
        'flagged_by',
        'flagged_at',
        'is_verified',   
        'verified_by',   
        'verified_at', 
        'is_mezmur',
        'is_mezmur_member',
        'promotion_status',
        'nominated_by',
        'nominated_at',
        'endorsed_by',
        'endorsed_at',
        'endorsement_notes',
        'approved_by',
        'approved_at',
        'target_section_id',
        'promotion_notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'birth_certificates' => 'array',
        'educational_certificates' => 'array',
        'is_mezmur' => 'boolean',
        'is_mezmur_member' => 'boolean',
        'is_verified' => 'boolean',
        'is_flagged' => 'boolean',
        'is_night' => 'boolean',
    ];
}
