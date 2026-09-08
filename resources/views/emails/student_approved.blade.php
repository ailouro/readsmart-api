<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #ffffff; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; border: 3px solid #000; box-shadow: 5px 5px 0px #000; }
        .header { color: #9B0505; font-size: 24px; font-weight: 900; text-align: center; margin-bottom: 20px; }
        .content { font-size: 16px; color: #333; line-height: 1.6; }
        .box { background-color: #FDE047; padding: 15px; border-radius: 8px; border: 2px solid #000; margin: 20px 0; text-align: center; }
        .highlight { font-weight: bold; color: #000; font-size: 18px; }
        .footer { text-align: center; font-size: 12px; color: #777; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">ReadSmart Account Approved! 🎉</div>
        <div class="content">
            <p>Hello Parent,</p>
            <p>Great news! The ReadSmart student account for <strong>{{ $student->name }}</strong> has been officially approved by the school administrator.</p>
            
            <p>Your child can now log in to the ReadSmart App using the credentials below:</p>

            <div class="box">
                <p><strong>LRN (Login ID):</strong> <span class="highlight">{{ $student->lrn }}</span></p>
                <p><strong>Password:</strong> <span class="highlight">{{ $defaultPassword }}</span></p>
            </div>

            <p>You can link your child's account to your Parent Dashboard to monitor their reading progress.</p>
            <p>Happy Reading!</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} San Vicente Elementary School - ReadSmart App.
        </div>
    </div>
</body>
</html>