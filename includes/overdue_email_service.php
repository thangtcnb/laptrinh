<?php
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/mailer.php';

/**
 * Scans all overdue tasks across the system and sends email notifications
 * to assignees (Assignee) and creator/managers (Assigner/Supervisor).
 *
 * Prevents sending duplicate notifications to the same recipient for the same task on the same day.
 *
 * @param PDO $pdo
 * @return array Array summarizing total overdue tasks checked, emails sent, and skipped duplicates.
 */
function checkAndSendOverdueTaskEmails($pdo) {
    $today = date('Y-m-d');
    $nowTimestamp = time();

    $stats = [
        'overdue_tasks_found' => 0,
        'emails_sent' => 0,
        'emails_skipped' => 0,
        'details' => []
    ];

    try {
        // Query tasks where due_date < current date and status != 'HOAN_THANH'
        $sql = "
            SELECT t.*,
                   d.name AS dept_name, d.code AS dept_code,
                   u.fullname AS creator_name, u.email AS creator_email, u.role AS creator_role
            FROM tasks t
            JOIN departments d ON t.department_id = d.id
            JOIN users u ON t.created_by = u.id
            WHERE t.due_date IS NOT NULL
              AND t.due_date < CURDATE()
              AND t.status != 'HOAN_THANH'
            ORDER BY t.due_date ASC, t.id ASC
        ";
        $stmt = $pdo->query($sql);
        $overdueTasks = $stmt->fetchAll();

        $stats['overdue_tasks_found'] = count($overdueTasks);

        if (empty($overdueTasks)) {
            return $stats;
        }

        // Helper statement to check existing email log for today
        $checkLogStmt = $pdo->prepare("
            SELECT COUNT(*) FROM overdue_email_logs
            WHERE task_id = ? AND user_id = ? AND sent_date = ?
        ");

        // Helper statement to record log
        $insertLogStmt = $pdo->prepare("
            INSERT IGNORE INTO overdue_email_logs (task_id, user_id, sent_date, sent_at)
            VALUES (?, ?, ?, NOW())
        ");

        // Helper statement to fetch task assignees
        $assigneesStmt = $pdo->prepare("
            SELECT ta.role_type, ta.user_id, u.fullname, u.email
            FROM task_assignees ta
            JOIN users u ON ta.user_id = u.id
            WHERE ta.task_id = ?
        ");

        $baseUrl = rtrim(defined('APP_URL') ? APP_URL : 'http://localhost', '/');

        foreach ($overdueTasks as $task) {
            $taskId = (int)$task['id'];
            $taskTitle = $task['title'];
            $dueDateStr = $task['due_date'];
            $dueDateTs = strtotime($dueDateStr . ' 23:59:59');

            // Calculate overdue duration (days / hours)
            $diffSecs = max(0, $nowTimestamp - strtotime($dueDateStr . ' 00:00:00'));
            $overdueDays = max(1, floor($diffSecs / 86400));
            $overdueHours = floor($diffSecs / 3600);

            $taskDetailUrl = $baseUrl . '/index.php?task_id=' . $taskId;

            // Gather all recipients: Assignees (MAIN & COOP) + Task Creator (Assigner)
            $assigneesStmt->execute([$taskId]);
            $assignees = $assigneesStmt->fetchAll();

            $recipients = [];

            // Add assignees
            foreach ($assignees as $assignee) {
                if (!empty($assignee['email']) && filter_var($assignee['email'], FILTER_VALIDATE_EMAIL)) {
                    $recipients[$assignee['user_id']] = [
                        'user_id' => (int)$assignee['user_id'],
                        'fullname' => $assignee['fullname'],
                        'email' => $assignee['email'],
                        'role_type' => $assignee['role_type'], // 'MAIN' or 'COOP'
                        'is_creator' => false
                    ];
                }
            }

            // Add Creator / Assigner
            if (!empty($task['creator_email']) && filter_var($task['creator_email'], FILTER_VALIDATE_EMAIL)) {
                $creatorId = (int)$task['created_by'];
                if (isset($recipients[$creatorId])) {
                    $recipients[$creatorId]['is_creator'] = true;
                } else {
                    $recipients[$creatorId] = [
                        'user_id' => $creatorId,
                        'fullname' => $task['creator_name'],
                        'email' => $task['creator_email'],
                        'role_type' => 'CREATOR',
                        'is_creator' => true
                    ];
                }
            }

            // Send notification email to each recipient if not already sent today
            foreach ($recipients as $recipient) {
                $recipientId = $recipient['user_id'];

                $checkLogStmt->execute([$taskId, $recipientId, $today]);
                if ((int)$checkLogStmt->fetchColumn() > 0) {
                    $stats['emails_skipped']++;
                    continue;
                }

                // Construct email content
                $emailSubject = "[CẢNH BÁO QUÁ HẠN] Công việc: " . $taskTitle;
                $htmlBody = buildOverdueEmailTemplate([
                    'recipient_name' => $recipient['fullname'],
                    'is_creator' => $recipient['is_creator'],
                    'task_id' => $taskId,
                    'task_title' => $taskTitle,
                    'creator_name' => $task['creator_name'],
                    'dept_name' => $task['dept_name'],
                    'due_date' => date('d/m/Y', strtotime($dueDateStr)),
                    'overdue_days' => $overdueDays,
                    'overdue_hours' => $overdueHours,
                    'task_url' => $taskDetailUrl,
                    'priority' => $task['priority'],
                    'status' => $task['status']
                ]);

                $sent = sendSystemEmail(
                    $recipient['email'],
                    $recipient['fullname'],
                    $emailSubject,
                    $htmlBody
                );

                if ($sent) {
                    $insertLogStmt->execute([$taskId, $recipientId, $today]);
                    $stats['emails_sent']++;
                    $stats['details'][] = "Sent to {$recipient['email']} for Task #{$taskId}";
                }
            }
        }

    } catch (\PDOException $e) {
        $stats['error'] = $e->getMessage();
    }

    return $stats;
}

/**
 * Builds professional, responsive HTML email template for overdue task warnings.
 */
function buildOverdueEmailTemplate($data) {
    $recipientName = htmlspecialchars($data['recipient_name']);
    $taskTitle = htmlspecialchars($data['task_title']);
    $creatorName = htmlspecialchars($data['creator_name']);
    $deptName = htmlspecialchars($data['dept_name']);
    $dueDate = htmlspecialchars($data['due_date']);
    $overdueDays = (int)$data['overdue_days'];
    $taskUrl = htmlspecialchars($data['task_url']);
    $isCreator = $data['is_creator'];
    $priorityLabel = ($data['priority'] === 'URGENT') ? 'KHẨN CẤP' : 'Bình thường';
    $priorityClass = ($data['priority'] === 'URGENT') ? '#dc2626' : '#475569';

    $roleText = $isCreator ? 'Người giao việc / Quản lý' : 'Người được giao việc';
    $actionText = $isCreator
        ? 'Vui lòng kiểm tra, đôn đốc và nhắc nhở nhân sự thực hiện để hoàn thành công việc đúng tiến độ.'
        : 'Công việc của bạn đã quá hạn hoàn thành. Vui lòng khẩn trương kiểm tra, xử lý và cập nhật tiến độ công việc trên hệ thống.';

    return '
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cảnh báo công việc quá hạn</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; color: #334155;">
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
            <tr>
                <td align="center" style="padding: 24px 12px;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 620px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">

                        <!-- Header Banner -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 24px 30px; text-align: left;">
                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td>
                                            <div style="color: #93c5fd; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">TRƯỜNG ĐẠI HỌC TÀI CHÍNH - MẮT HẢI (UFM)</div>
                                            <div style="color: #ffffff; font-size: 18px; font-weight: 800; margin-top: 4px;">PHÂN HIỆU UFM TẠI TP. QUẢNG NGÃI</div>
                                            <div style="color: #e0f2fe; font-size: 13px; margin-top: 2px;">Hệ thống Phân công & Quản lý Công việc</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Alert Ribbon -->
                        <tr>
                            <td style="background-color: #fef2f2; border-bottom: 2px solid #fca5a5; padding: 14px 30px;">
                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td width="32" style="vertical-align: middle;">
                                            <span style="display: inline-block; width: 28px; height: 28px; background-color: #dc2626; color: #ffffff; border-radius: 50%; text-align: center; line-height: 28px; font-weight: bold; font-size: 14px;">!</span>
                                        </td>
                                        <td style="vertical-align: middle; padding-left: 8px;">
                                            <span style="color: #991b1b; font-size: 15px; font-weight: 700;">THÔNG BÁO CÔNG VIỆC ĐÃ QUÁ HẠN</span>
                                            <span style="display: inline-block; background-color: #dc2626; color: #ffffff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; margin-left: 6px;">Quá ' . $overdueDays . ' ngày</span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Body Content -->
                        <tr>
                            <td style="padding: 28px 30px;">
                                <p style="font-size: 15px; margin-top: 0; margin-bottom: 16px; color: #1e293b;">
                                    Kính gửi <strong>' . $recipientName . '</strong> (' . $roleText . '),
                                </p>
                                <p style="font-size: 14px; line-height: 1.6; color: #475569; margin-bottom: 20px;">
                                    Hệ thống ghi nhận công việc dưới đây đã <strong>vượt quá thời hạn hoàn thành</strong> ban đầu. ' . $actionText . '
                                </p>

                                <!-- Task Details Card -->
                                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 24px; border-left: 4px solid #dc2626;">
                                    <tr>
                                        <td style="padding: 16px 20px;">
                                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                                <tr>
                                                    <td style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Tên công việc / Nhiệm vụ:</td>
                                                </tr>
                                                <tr>
                                                    <td style="font-size: 16px; font-weight: 700; color: #0f172a; padding-top: 4px; padding-bottom: 12px;">
                                                        ' . $taskTitle . '
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>
                                                        <table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-top: 1px dashed #cbd5e1; padding-top: 12px;">
                                                            <tr>
                                                                <td width="50%" style="font-size: 13px; color: #475569; padding-bottom: 6px;">
                                                                    <strong>Đơn vị thực hiện:</strong> ' . $deptName . '
                                                                </td>
                                                                <td width="50%" style="font-size: 13px; color: #475569; padding-bottom: 6px;">
                                                                    <strong>Người giao việc:</strong> ' . $creatorName . '
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td style="font-size: 13px; color: #475569; padding-bottom: 6px;">
                                                                    <strong>Hạn chót ban đầu:</strong> <span style="color: #dc2626; font-weight: 700;">' . $dueDate . '</span>
                                                                </td>
                                                                <td style="font-size: 13px; color: #475569; padding-bottom: 6px;">
                                                                    <strong>Mức độ ưu tiên:</strong> <span style="color: ' . $priorityClass . '; font-weight: 700;">' . $priorityLabel . '</span>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="2" style="font-size: 13px; color: #dc2626; font-weight: 700; padding-top: 4px;">
                                                                    Thời gian đã trễ hạn: ' . $overdueDays . ' ngày
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <!-- CTA Button -->
                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td align="center" style="padding: 8px 0 20px 0;">
                                            <a href="' . $taskUrl . '" target="_blank" style="display: inline-block; background-color: #2563eb; color: #ffffff; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 8px; box-shadow: 0 2px 6px rgba(37,99,235,0.3);">
                                                Truy cập Trang chi tiết công việc &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="font-size: 12px; color: #94a3b8; line-height: 1.5; margin: 0; text-align: center;">
                                    <i>(Nếu nút trên không hoạt động, bạn có thể copy và dán đường dẫn này vào trình duyệt: <a href="' . $taskUrl . '" style="color: #2563eb;">' . $taskUrl . '</a>)</i>
                                </p>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 30px; text-align: center;">
                                <p style="font-size: 12px; color: #64748b; margin: 0 0 6px 0; font-weight: 600;">
                                    Phân hiệu Trường Đại học Tài chính - Mắt hải tại TP. Quảng Ngãi
                                </p>
                                <p style="font-size: 11px; color: #94a3b8; margin: 0;">
                                    Địa chỉ: Số 01 Lý Thường Kiệt, TP. Quảng Ngãi | Email: ufmquangngai@ufm.edu.vn
                                </p>
                                <p style="font-size: 11px; color: #cbd5e1; margin-top: 10px; margin-bottom: 0;">
                                    Email này được gửi tự động từ Hệ thống Phân công Công việc UFM. Vui lòng không phản hồi lại email này.
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ';
}
