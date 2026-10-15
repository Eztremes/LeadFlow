<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Lead Import Results</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h2>Lead Import Processing Completed</h2>

    <p>Your CSV import has finished processing.</p>

    <p>
        Some records could not be imported because
        they were invalid or duplicated.
    </p>

    <p>
        Please find the attached CSV containing
        the failed records and their failure reasons.
    </p>

    <p>Import ID: #{{ $importId }}</p>

    <p>Regards,<br>Lead Import System</p>
</body>
</html>
