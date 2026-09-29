<?php

namespace App;

enum FileUploadStatus: string
{
    case PendingUpload = 'pending_upload';
    case Uploaded = 'uploaded';
    case Failed = 'failed';
    case DeletePending = 'delete_pending';
}
