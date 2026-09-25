<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgentAvatarService
{
    public const SIZE = 256;

    /** Store a normalized square avatar and remove any previous custom image. */
    public function replace(Agent $agent, UploadedFile $file): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages([
                'avatar' => 'The selected file is not a readable image.',
            ]);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);
            throw ValidationException::withMessages([
                'avatar' => 'The selected image has invalid dimensions.',
            ]);
        }

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        $side = min($sourceWidth, $sourceHeight);
        $sourceX = (int) floor(($sourceWidth - $side) / 2);
        $sourceY = (int) floor(($sourceHeight - $side) / 2);
        imagecopyresampled($canvas, $source, 0, 0, $sourceX, $sourceY, self::SIZE, self::SIZE, $side, $side);
        imagedestroy($source);

        ob_start();
        $extension = function_exists('imagewebp') ? 'webp' : 'jpg';
        if ($extension === 'webp') {
            imagewebp($canvas, null, 84);
        } else {
            imagejpeg($canvas, null, 88);
        }
        $contents = (string) ob_get_clean();
        imagedestroy($canvas);

        $path = $agent->user_id.'/'.$agent->id.'/'.Str::uuid().'.'.$extension;
        Storage::disk('agent-avatars')->put($path, $contents);

        $this->delete($agent);
        $agent->update(['avatar_path' => $path]);
    }

    public function delete(Agent $agent): void
    {
        if ($agent->avatar_path) {
            Storage::disk('agent-avatars')->delete($agent->avatar_path);
        }

        if ($agent->avatar_path !== null) {
            $agent->update(['avatar_path' => null]);
        }
    }
}
