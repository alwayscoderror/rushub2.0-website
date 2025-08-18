<?php
// php/send_email.php — обработчик под форму:
// name, surname, email, (опционально email_confirm), message

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function respondJson(bool $success, int $status, string $message, array $extra = []): void {
	http_response_code($status);
	echo json_encode(array_merge([
		'success' => $success,
		'message' => $message,
	], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	respondJson(false, 405, 'Метод не поддерживается. Используйте POST.');
}

$name          = trim($_POST['name']           ?? '');
$surname       = trim($_POST['surname']        ?? '');
$email         = trim($_POST['email']          ?? '');
$emailConfirm  = trim($_POST['email_confirm']  ?? ''); // может не приходить — делаем опциональным
$message       = trim($_POST['message']        ?? '');

if ($name === '' || $surname === '' || $email === '' || $message === '') {
	respondJson(false, 422, 'Пожалуйста, заполните все обязательные поля.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
	respondJson(false, 422, 'Некорректный адрес email.');
}
if ($emailConfirm !== '' && strcasecmp($email, $emailConfirm) !== 0) {
	respondJson(false, 422, 'Email адреса не совпадают.');
}

$subject = 'Новое сообщение с сайта';
$body = implode("\n", [
	'Имя: ' . $name . ' ' . $surname,
	'Email: ' . $email,
	'',
	'Сообщение:',
	$message,
	'',
	'Дата: ' . date('Y-m-d H:i:s'),
	'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'неизвестно'),
]);

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
	require_once $vendorAutoload;
	// Значения берутся из окружения, а если их нет — используются дефолты Mailtrap, которые вы предоставили
	$smtpHost = getenv('SMTP_HOST') ?: 'sandbox.smtp.mailtrap.io';
	$smtpPort = (int)(getenv('SMTP_PORT') ?: 2525);
	$smtpUser = getenv('SMTP_USER') ?: getenv('MAILTRAP_USERNAME') ?: 'f6b346f2264d16';
	$smtpPass = getenv('SMTP_PASS') ?: getenv('MAILTRAP_PASSWORD') ?: 'f92f2a1b2ad33c';

	try {
		$phpmailer = new \PHPMailer\PHPMailer\PHPMailer(true);
		$phpmailer->isSMTP();
		$phpmailer->Host       = $smtpHost;
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Port       = $smtpPort;
		$phpmailer->Username   = $smtpUser;
		$phpmailer->Password   = $smtpPass;
		$phpmailer->CharSet    = 'UTF-8';
		$phpmailer->Encoding   = 'base64';

		// В продакшене поставьте адрес с вашего домена
		$phpmailer->setFrom('no-reply@example.test', 'Сайт');
		$phpmailer->addAddress('inbox@example.test', 'Получатель'); // для Mailtrap можно любой
		$phpmailer->addReplyTo($email, $name . ' ' . $surname);

		$phpmailer->Subject = $subject;
		$phpmailer->Body    = $body;

		$phpmailer->send();
		respondJson(true, 200, 'Сообщение отправлено. Проверьте Mailtrap Inbox.');
	} catch (\Throwable $e) {
		respondJson(false, 500, 'Ошибка SMTP: ' . $e->getMessage());
	}
}

respondJson(false, 500, 'PHPMailer не найден. Установите: composer require phpmailer/phpmailer');