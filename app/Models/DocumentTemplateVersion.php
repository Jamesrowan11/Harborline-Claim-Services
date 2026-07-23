<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplateVersion extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime'];

    public function template() { return $this->belongsTo(DocumentTemplate::class, 'document_template_id'); }
}
