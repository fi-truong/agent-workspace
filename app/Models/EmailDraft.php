<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmailDraft extends Model { protected $fillable = ['user_id','conversation_id','recipients','subject','body','attachments']; protected function casts(): array { return ['recipients'=>'array','attachments'=>'array']; } }
