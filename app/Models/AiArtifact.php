<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AiArtifact extends Model { protected $fillable = ['user_id','conversation_id','name','mime_type','path','size']; }
