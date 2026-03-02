<?php
/**
 * Inbox Master - Redirect to Next.js Inbox
 * Opens inbox.re-ya.com in a new window
 */
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กำลังเปิด Inbox...</title>
    <style>
        body {
            font-family: 'Inter', 'Noto Sans Thai', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f1f5f9;
            color: #374151;
        }

        .redirect-box {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }

        .redirect-box h2 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .redirect-box p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .redirect-box a {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 24px;
            background: #0C665D;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }

        .redirect-box a:hover {
            background: #0a5650;
        }
    </style>
</head>

<body>
    <div class="redirect-box">
        <h2>กำลังเปิดแชทหลัก...</h2>
        <p>หากหน้าต่างใหม่ไม่เปิดขึ้น กรุณาคลิกปุ่มด้านล่าง</p>
        <a href="https://inbox.re-ya.com" target="_blank" rel="noopener">เปิดแชทหลัก</a>
    </div>
    <script>
        window.open('https://inbox.re-ya.com', '_blank');
    </script>
</body>

</html>