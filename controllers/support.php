<?php

declare(strict_types=1);

final class support extends controller
{
    public function index(array $params = []): void
    {
        /** @var support_model $model */
        $model = $this->model('support_model');

        if ($model->databaseState() !== 'ready') {
            http_response_code(503);
            $this->view('index', ['available' => false, 'error' => null, 'topics' => []]);
            return;
        }

        $topics = $model->getTopics(true);
        $data = ['available' => true, 'error' => null, 'topics' => $topics];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $type = strtolower(trim((string) ($_POST['type'] ?? 'general')));
            $subject = trim((string) ($_POST['subject'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $allowedTypes = array_column($topics, 'value');

            if (!in_array($type, $allowedTypes, true)) {
                $data['error'] = 'Please select a valid Support topic.';
            } elseif ($name === '' || $subject === '' || $description === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $data['error'] = 'Please complete all required fields.';
            } else {
                $ticketId = $model->createTicket(compact('name', 'email', 'type', 'subject', 'description'));
                $ticket = $model->findTicket($ticketId);
                if ($ticket !== null) {
                    $this->sendNewTicketNotifications($model, $ticket);
                    $this->sendSlackTicketCreated($model, $ticketId);
                }
                header('Location: /support/ticket/' . rawurlencode($ticketId));
                exit;
            }
        }

        $this->view('index', $data);
    }

    private function sendSlackTicketCreated(support_model $model, string $ticketId): void
    {
        $config = $model->getConfig();
        $slack = is_array($config['slack'] ?? null) ? $config['slack'] : [];
        if (empty($slack['enabled'])) {
            return;
        }

        $webhook = trim((string) ($slack['webhook_url'] ?? ''));
        if ($webhook === '') {
            return;
        }

        try {
            require_once dirname(__DIR__) . '/libraries/slack_notifier.php';
            $notifier = new slack_notifier($webhook);
            $notifier->send(
                'SUPPORT: #' . $ticketId . ' Ticket Created https://www.stn-chain.org/support/ticket/' . rawurlencode($ticketId)
            );
        } catch (Throwable $exception) {
            error_log('STNC Support Slack notification failed: ' . $exception->getMessage());
        }
    }

    private function sendNewTicketNotifications(support_model $model, array $ticket): void
    {
        $id = (string) $ticket['ticket_id'];
        $identity = $this->supportIdentity($model);

        if ($identity['email'] !== '') {
            $this->sendMail(
                $model,
                $identity['email'],
                $identity['name'],
                '[Support #' . $id . '] ' . (string) $ticket['subject'],
                '<h2>New Support Ticket #' . $this->escapeHtml($id) . '</h2><p><a href="' . URLROOT . '/admin/support/' . rawurlencode($id) . '">Open ticket in Admin</a></p>'
            );
        }

        $this->sendMail(
            $model,
            (string) $ticket['email'],
            (string) $ticket['name'],
            '[Support #' . $id . '] Ticket received',
            '<p>Your Support ticket <strong>#' . $this->escapeHtml($id) . '</strong> has been received.</p><p><a href="' . URLROOT . '/support/ticket/' . rawurlencode($id) . '">View your Support ticket</a></p>'
        );
    }

    private function sendMail(support_model $model, string $address, string $name, string $subject, string $body): void
    {
        try {
            $mailObj = new mailer();
            $mail = $mailObj->create();
            $identity = $this->supportIdentity($model);
            if ($identity['email'] !== '') {
                $mail->setFrom($identity['email'], $identity['name']);
            }
            $mail->addAddress($address, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
        } catch (Throwable $exception) {
            error_log('STNC Support mail notification failed: ' . $exception->getMessage());
        }
    }

    private function supportIdentity(support_model $model): array
    {
        $config = $model->getConfig();
        $mail = is_array($config['mail'] ?? null) ? $config['mail'] : [];
        $site = $this->siteIdentity();
        $name = trim((string) ($mail['from_name'] ?? ''));
        $email = trim((string) ($mail['from_email'] ?? ''));

        if ($name === '') {
            $name = $site['name'] !== '' ? $site['name'] : 'Support';
        }
        if ($email === '') {
            $email = $site['email'];
        }

        return ['name' => $name, 'email' => $email];
    }

    private function siteIdentity(): array
    {
        global $SITE;
        $site = is_array($SITE ?? null) ? $SITE : [];
        $name = trim((string) ($site['name'] ?? $site['site_name'] ?? $site['title'] ?? ''));
        $email = trim((string) ($site['email'] ?? $site['site_email'] ?? $site['contact_email'] ?? ''));
        return ['name' => $name, 'email' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : ''];
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
