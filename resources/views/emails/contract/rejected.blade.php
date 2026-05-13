<!DOCTYPE html>
<html>
<head>
    <title>Pemberitahuan Penolakan Kontrak</title>
</head>
<body>
    <h2>Kontrak Ditolak</h2>
    <p>Halo,</p>
    <p>Kami menginformasikan bahwa kontrak dengan nomor <strong>{{ $contract->contract_number }}</strong> telah ditolak oleh Manager kami.</p>
    
    <p><strong>Alasan Penolakan:</strong></p>
    <blockquote>{{ $reason }}</blockquote>
    
    <p>Proses untuk kontrak ini telah dihentikan.</p>
    <p>Terima kasih,</p>
    <p>Tim Agreema</p>
</body>
</html>
