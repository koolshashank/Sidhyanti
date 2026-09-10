<?php
/**
 * Shared file-upload handling for every mobile document/photo capture flow
 * (KYC, GRT, CGT, Death Document proofs). All new uploads go under
 * Document_images/{category}/{subfolder}/ at the repo root -- one place,
 * one naming convention, instead of each endpoint inventing its own
 * uploads/xxx_yyy/ folder.
 *
 * Legacy documents (migrated from the old Node-based file server) keep
 * whatever DocFilePath they already have (typically
 * http://168.220.248.128:3000/uploads/... or /GRT_Images/...) -- this
 * helper is only for files uploaded through this codebase from now on.
 * Nothing needs to "fall back" between the two at read time: each document
 * row's DocFilePath is already the one correct, working URL for that row:
 * old rows keep pointing at the old server, new rows point here, and a
 * fresh upload for the same member+type simply replaces the row (see each
 * endpoint's upsert), so whichever is current is always what gets shown.
 */

/**
 * Saves an uploaded file (from $_FILES['field']) under
 * Document_images/{category}/{subfolder}/ and returns its public URL, or
 * null if no file was actually sent. Throws ApiError on a real failure
 * (bad extension, disk write failure) so callers can let it bubble up to
 * their existing catch block.
 *
 * $category: 'KYC' | 'GRT' | 'CGT' | 'Death_Document'
 * $subfolder: the owning entity's id (MemberID, LoanID, CenterID, ...) --
 * kept as its own path segment so a member/loan/center's documents stay
 * grouped together on disk.
 */
function store_document_upload(array $fileField, string $category, string $subfolder, string $filenamePrefix): ?string {
    if (!isset($fileField['error']) || $fileField['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($fileField['error'] !== UPLOAD_ERR_OK) {
        throw new ApiError(422, 'File upload failed');
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($fileField['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new ApiError(422, 'File must be an image (jpg/png) or PDF');
    }

    $relativeDir = "Document_images/$category/$subfolder";
    $uploadDir = __DIR__ . '/../../' . $relativeDir . '/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new ApiError(500, 'Failed to prepare upload directory');
    }

    $filename = $filenamePrefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($fileField['tmp_name'], $uploadDir . $filename)) {
        throw new ApiError(500, 'Failed to save uploaded file');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . '/sidhyanti-crm-v2/' . $relativeDir . '/' . $filename;
}
