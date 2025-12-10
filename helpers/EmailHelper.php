<?php
// Load PHPMailer via Composer autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Load environment variables from .env file
if (file_exists(__DIR__ . '/../.env')) {
    $envFile = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envFile as $line) {
        if (strpos(trim($line), '#') === 0)
            continue; // Skip comments
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!getenv($key)) {
            putenv("$key=$value");
        }
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHelper
{
    private $mailer;
    private $fromEmail;
    private $fromName;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        // SMTP Configuration
        $this->mailer->isSMTP();
        $this->mailer->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = getenv('SMTP_USERNAME') ?: '';
        $this->mailer->Password = getenv('SMTP_PASSWORD') ?: '';
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = getenv('SMTP_PORT') ?: 587;
        $this->mailer->CharSet = 'UTF-8';

        // From email
        $this->fromEmail = getenv('SMTP_FROM_EMAIL') ?: getenv('SMTP_USERNAME');
        $this->fromName = getenv('SMTP_FROM_NAME') ?: 'Recruitment App';
    }

    /**
     * Send application status update email to candidate
     */
    public function sendApplicationStatusUpdate($candidateEmail, $candidateName, $jobTitle, $companyName, $status, $additionalInfo = [])
    {
        try {
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($candidateEmail, $candidateName);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = $this->getSubjectByStatus($status, $companyName);
            $this->mailer->Body = $this->getEmailBodyByStatus($candidateName, $jobTitle, $companyName, $status, $additionalInfo);
            $this->mailer->AltBody = strip_tags($this->mailer->Body);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get email subject based on application status
     */
    private function getSubjectByStatus($status, $companyName)
    {
        $subjects = [
            'pending' => "Đơn ứng tuyển của bạn đang được xem xét - {$companyName}",
            'reviewed' => "Đơn ứng tuyển của bạn đã được xem xét - {$companyName}",
            'shortlisted' => "Chúc mừng! Bạn đã được chọn vào danh sách ứng viên tiềm năng - {$companyName}",
            'interview' => "Mời phỏng vấn từ {$companyName}",
            'rejected' => "Thông báo về đơn ứng tuyển của bạn - {$companyName}",
            'hired' => "Chúc mừng! Bạn đã được tuyển dụng - {$companyName}"
        ];

        return $subjects[$status] ?? "Cập nhật trạng thái đơn ứng tuyển - {$companyName}";
    }

    /**
     * Get email body based on application status
     */
    private function getEmailBodyByStatus($candidateName, $jobTitle, $companyName, $status, $additionalInfo)
    {
        $greeting = "<p>Xin chào <strong>{$candidateName}</strong>,</p>";
        $jobInfo = "<p>Vị trí ứng tuyển: <strong>{$jobTitle}</strong> tại <strong>{$companyName}</strong></p>";

        $statusMessages = [
            'pending' => "
                <p>Đơn ứng tuyển của bạn đang được xem xét bởi đội ngũ tuyển dụng của chúng tôi.</p>
                <p>Chúng tôi sẽ liên hệ với bạn sớm nhất có thể.</p>
            ",
            'reviewed' => "
                <p>Đơn ứng tuyển của bạn đã được xem xét.</p>
                <p>Chúng tôi sẽ thông báo cho bạn về các bước tiếp theo trong thời gian sớm nhất.</p>
            ",
            'shortlisted' => "
                <p>Chúc mừng! Bạn đã được chọn vào danh sách ứng viên tiềm năng.</p>
                <p>Chúng tôi rất ấn tượng với hồ sơ của bạn và muốn tìm hiểu thêm về bạn.</p>
                <p>Vui lòng chờ thông tin về lịch phỏng vấn trong thời gian tới.</p>
            ",
            'interview' => $this->getInterviewMessage($additionalInfo),
            'rejected' => $this->getRejectionMessage($additionalInfo),
            'hired' => "
                <p><strong>Chúc mừng!</strong> Chúng tôi rất vui mừng thông báo rằng bạn đã được chọn cho vị trí này.</p>
                <p>Đội ngũ nhân sự sẽ liên hệ với bạn trong thời gian sớm nhất để thảo luận về các bước tiếp theo.</p>
                <p>Chào mừng bạn đến với đội ngũ của chúng tôi!</p>
            "
        ];

        $message = $statusMessages[$status] ?? "<p>Trạng thái đơn ứng tuyển của bạn đã được cập nhật.</p>";

        $footer = "
            <hr>
            <p style='color: #666; font-size: 12px;'>
                Email này được gửi tự động từ hệ thống tuyển dụng. Vui lòng không trả lời email này.<br>
                Nếu bạn có bất kỳ câu hỏi nào, vui lòng liên hệ trực tiếp với {$companyName}.
            </p>
        ";

        return $greeting . $jobInfo . $message . $footer;
    }

    /**
     * Get interview message with details
     */
    private function getInterviewMessage($info)
    {
        $message = "<p><strong>Chúc mừng!</strong> Chúng tôi muốn mời bạn tham gia phỏng vấn.</p>";

        if (!empty($info['interview_date'])) {
            $message .= "<p><strong>Thời gian:</strong> " . date('d/m/Y H:i', strtotime($info['interview_date'])) . "</p>";
        }

        if (!empty($info['interview_location'])) {
            $message .= "<p><strong>Địa điểm:</strong> {$info['interview_location']}</p>";
        }

        if (!empty($info['interview_notes'])) {
            $message .= "<p><strong>Ghi chú:</strong> {$info['interview_notes']}</p>";
        }

        $message .= "<p>Vui lòng xác nhận sự tham gia của bạn và chuẩn bị đầy đủ hồ sơ.</p>";

        return $message;
    }

    /**
     * Get rejection message
     */
    private function getRejectionMessage($info)
    {
        $message = "
            <p>Cảm ơn bạn đã quan tâm đến vị trí này tại công ty chúng tôi.</p>
            <p>Sau khi xem xét kỹ lưỡng, chúng tôi rất tiếc phải thông báo rằng chúng tôi đã quyết định tiếp tục với các ứng viên khác phù hợp hơn với yêu cầu hiện tại.</p>
        ";

        if (!empty($info['rejection_reason'])) {
            $message .= "<p><strong>Lý do:</strong> {$info['rejection_reason']}</p>";
        }

        $message .= "
            <p>Chúng tôi đánh giá cao thời gian và sự quan tâm của bạn. Chúng tôi khuyến khích bạn tiếp tục theo dõi các cơ hội việc làm khác phù hợp với bạn.</p>
            <p>Chúc bạn thành công trong sự nghiệp!</p>
        ";

        return $message;
    }

    /**
     * Clear all recipients for reuse
     */
    public function clearRecipients()
    {
        $this->mailer->clearAddresses();
        $this->mailer->clearAttachments();
    }
}
