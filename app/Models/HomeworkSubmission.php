<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HomeworkSubmission extends Model
{
    protected $fillable = [
        'homework_id','student_school_year_id',
        'file_path','file_name','file_size',
        'submitted_by','submitted_at',
        'status','grade','teacher_comment','graded_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at'    => 'datetime',
    ];

    public function homework()         { return $this->belongsTo(Homework::class); }
    public function studentSchoolYear(){ return $this->belongsTo(StudentSchoolYear::class); }
    public function submittedBy()      { return $this->belongsTo(User::class, 'submitted_by'); }

    public function fileUrl(): string
    {
        return asset('storage/'.$this->file_path);
    }

    public function isLate(): bool
    {
        return $this->submitted_at > $this->homework->due_date;
    }
}