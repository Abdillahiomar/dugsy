<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDocument extends Model {
    protected $fillable = [
        'student_school_year_id','required_document_id',
        'file_path','original_name','status','rejected_reason','provided_at',
    ];
    protected $casts = ['provided_at' => 'datetime'];
 
    public function studentSchoolYear() { return $this->belongsTo(StudentSchoolYear::class); }
    public function requiredDocument()  { return $this->belongsTo(RequiredDocument::class); }
 
    public function fileUrl(): ?string {
        return $this->file_path ? asset('storage/'.$this->file_path) : null;
    }
    public function extension(): ?string {
        return $this->file_path
            ? strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION))
            : null;
    }
}