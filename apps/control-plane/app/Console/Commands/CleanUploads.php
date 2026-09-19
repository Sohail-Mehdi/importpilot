<?php
namespace App\\Console\\Commands;

use Illuminate\\Console\\Command;
use App\\Models\\UploadAttempt;
use Illuminate\\Support\\Facades\\Storage;

class CleanUploads extends Command
{
    protected $signature = 'importpilot:clean-uploads';
    protected $description = 'Clean up expired and rejected temporary uploads from S3';

    public function handle()
    {
        $attempts = UploadAttempt::whereIn('status', ['EXPIRED', 'REJECTED'])
            ->orWhere(function ($q) {
                $q->where('status', 'PENDING')->where('expires_at', '<', now());
            })->get();

        $disk = Storage::disk('s3');
        $deleted = 0;

        foreach ($attempts as $attempt) {
            if ($disk->exists($attempt->storage_key)) {
                $disk->delete($attempt->storage_key);
            }
            // Mark deleted or keep status? The prompt says "deletion mechanism... auditable"
            $attempt->update(['status' => 'DELETED']);
            $deleted++;
        }

        $this->info("Cleaned up {$deleted} orphaned uploads.");
    }
}
