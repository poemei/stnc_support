<?php

declare(strict_types=1);

final class support extends controller
{
    private const ADMIN_ACTIONS = [
        'install_sql',
        'save_config',
        'test_slack',
        'status',
        'tier',
        'assign',
        'reply',
        'delete_data',
    ];

    public function index(array $params = []): void
    {
        /** @var support_model $model */
        $model = $this->model('support_model');

        if ($model->databaseState() !== 'ready') {
            http_response_code(503);
            $this->view('index', [
                'available' => false,
                'error' => null,
                'topics' => [],
            ]);
            return;
        }

        $topics = $model->getTopics(true);

        $data = [
            'available' => true,
            'error' => null,
            'topics' => $topics,
        ];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();

            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $type = strtolower(
                trim((string) ($_POST['type'] ?? 'general'))
            );
            $subject = trim((string) ($_POST['subject'] ?? ''));
            $description = trim(
                (string) ($_POST['description'] ?? '')
            );

            $allowedTypes = array_column($topics, 'value');

            if (!in_array($type, $allowedTypes, true)) {
                $data['error'] =
                    'Please select a valid Support topic.';
            } elseif (
                $name === '' ||
                $subject === '' ||
                $description === '' ||
                filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                $data['error'] =
                    'Please complete all required fields.';
            } else {
                $ticketId = $model->createTicket(
                    compact(
                        'name',
                        'email',
                        'type',
                        'subject',
                        'description'
                    )
                );

                $ticket = $model->findTicket($ticketId);

                if ($ticket !== null) {
                    $this->sendNewTicketNotifications(
                        $model,
                        $ticket
                    );

                    $this->sendSlackTicketCreated(
                        $model,
                        $ticketId
                    );
                }

                header(
                    'Location: /support/ticket/' .
                    rawurlencode($ticketId)
                );
                exit;
            }
        }

        $this->view('index', $data);
    }

    public function ticket(array $params = []): void
    {
        /** @var support_model $model */
        $model = $this->model('support_model');

        if ($model->databaseState() !== 'ready') {
            http_response_code(503);

            $this->view('ticket', [
                'available' => false,
                'ticket' => null,
                'replies' => [],
            ]);

            return;
        }

        $ticketId = strtolower(
            trim((string) ($params[0] ?? ''))
        );

        if (!preg_match('/^[a-f0-9]{6}$/', $ticketId)) {
            http_response_code(404);

            $this->view('ticket', [
                'available' => true,
                'ticket' => null,
                'replies' => [],
            ]);

            return;
        }

        $ticket = $model->findTicket($ticketId);

        if ($ticket === null) {
            http_response_code(404);

            $this->view('ticket', [
                'available' => true,
                'ticket' => null,
                'replies' => [],
            ]);

            return;
        }

        $this->view('ticket', [
            'available' => true,
            'ticket' => $ticket,
            'replies' => $model->getPublicReplies(
                (int) $ticket['id']
            ),
        ]);
    }

    public function admin(array $params = []): void
    {
        $this->require_admin(7);

        /** @var support_model $model */
        $model = $this->model('support_model');

        $state = $model->databaseState();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();

            $action = trim(
                (string) ($_POST['action'] ?? '')
            );

            if (!in_array(
                $action,
                self::ADMIN_ACTIONS,
                true
            )) {
                http_response_code(400);

                $this->error_page(
                    'Invalid Support administrative action.'
                );
            }

            if ($action === 'save_config') {
                $this->saveConfiguration($model);

                header(
                    'Location: /admin/support?configured=1'
                );
                exit;
            }

            if ($action === 'test_slack') {
                $this->testSlack($model);

                header(
                    'Location: /admin/support?slack_tested=1'
                );
                exit;
            }

            if ($action === 'install_sql') {
                if ($state !== 'missing') {
                    http_response_code(409);

                    $this->error_page(
                        'The Support schema is already installed.'
                    );
                }

                $model->installSchema();

                if ($model->databaseState() !== 'ready') {
                    throw new RuntimeException(
                        'Support schema installation did not complete.'
                    );
                }

                header(
                    'Location: /admin/support?installed=1'
                );
                exit;
            }

            if ($state !== 'ready') {
                http_response_code(409);

                $this->error_page(
                    'The Support database is not available.'
                );
            }

            if ($action === 'delete_data') {
                $model->deleteData();

                header(
                    'Location: /admin/support?deleted=1'
                );
                exit;
            }

            $ticketId = strtolower(
                trim(
                    (string) (
                        $_POST['ticket_id'] ?? ''
                    )
                )
            );

            if (!preg_match(
                '/^[a-f0-9]{6}$/',
                $ticketId
            )) {
                http_response_code(400);

                $this->error_page(
                    'Invalid Support ticket identifier.'
                );
            }

            $ticket = $model->findTicket($ticketId);

            if ($ticket === null) {
                http_response_code(404);

                $this->error_page(
                    'The requested Support ticket was not found.'
                );
            }

            $this->handleTicketAction(
                $model,
                $ticket,
                $action
            );

            header(
                'Location: /admin/support/' .
                rawurlencode($ticketId)
            );
            exit;
        }

        $site = $this->siteIdentity();

        if ($state !== 'ready') {
            $this->view('admin/index', [
                'database_state' => $state,
                'tickets' => [],
                'config' => $model->getConfig(),
                'site_from_name' => $site['name'],
                'site_from_email' => $site['email'],
            ]);

            return;
        }

        $ticketId = strtolower(
            trim((string) ($params[1] ?? ''))
        );

        if ($ticketId === '') {
            $this->view('admin/index', [
                'database_state' => $state,
                'tickets' => $model->getTickets(),
                'config' => $model->getConfig(),
                'site_from_name' => $site['name'],
                'site_from_email' => $site['email'],
            ]);

            return;
        }

        if (!preg_match(
            '/^[a-f0-9]{6}$/',
            $ticketId
        )) {
            http_response_code(404);

            $this->view('admin/ticket', [
                'database_state' => $state,
                'ticket' => null,
                'replies' => [],
                'events' => [],
            ]);

            return;
        }

        $ticket = $model->findTicket($ticketId);

        if ($ticket === null) {
            http_response_code(404);

            $this->view('admin/ticket', [
                'database_state' => $state,
                'ticket' => null,
                'replies' => [],
                'events' => [],
            ]);

            return;
        }

        $this->view('admin/ticket', [
            'database_state' => $state,
            'ticket' => $ticket,
            'replies' => $model->getReplies(
                (int) $ticket['id']
            ),
            'events' => $model->getEvents(
                (int) $ticket['id']
            ),
        ]);
    }

    private function saveConfiguration(
        support_model $model
    ): void {
        $fromName = trim(
            (string) ($_POST['from_name'] ?? '')
        );

        $fromEmail = trim(
            (string) ($_POST['from_email'] ?? '')
        );

        if (
            $fromEmail !== '' &&
            filter_var(
                $fromEmail,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            http_response_code(400);

            $this->error_page(
                'Invalid Support From email address.'
            );
        }

        $topics = [];

        foreach (
            (array) ($_POST['topics'] ?? [])
            as $topic
        ) {
            if (
                !is_array($topic) ||
                !empty($topic['remove'])
            ) {
                continue;
            }

            $value = strtolower(
                trim(
                    (string) (
                        $topic['value'] ?? ''
                    )
                )
            );

            $label = trim(
                (string) (
                    $topic['label'] ?? ''
                )
            );

            if (
                $value === '' ||
                $label === ''
            ) {
                continue;
            }

            if (!preg_match(
                '/^[a-z0-9_-]+$/',
                $value
            )) {
                http_response_code(400);

                $this->error_page(
                    'Support topic values may contain only lowercase letters, numbers, underscores, and hyphens.'
                );
            }

            $topics[$value] = [
                'value' => $value,
                'label' => $label,
                'enabled' =>
                    (
                        (string) (
                            $topic['enabled'] ?? '0'
                        )
                    ) === '1',
            ];
        }

        $newValue = strtolower(
            trim(
                (string) (
                    $_POST['new_topic_value'] ?? ''
                )
            )
        );

        $newLabel = trim(
            (string) (
                $_POST['new_topic_label'] ?? ''
            )
        );

        if (
            $newValue !== '' ||
            $newLabel !== ''
        ) {
            if (
                $newValue === '' ||
                $newLabel === '' ||
                !preg_match(
                    '/^[a-z0-9_-]+$/',
                    $newValue
                )
            ) {
                http_response_code(400);

                $this->error_page(
                    'A new Support topic requires a valid value and label.'
                );
            }

            $topics[$newValue] = [
                'value' => $newValue,
                'label' => $newLabel,
                'enabled' =>
                    isset(
                        $_POST['new_topic_enabled']
                    ),
            ];
        }

        if ($topics === []) {
            http_response_code(400);

            $this->error_page(
                'Support must have at least one topic.'
            );
        }

        /*
         * Preserve the stored webhook unless the
         * administrator explicitly supplies a replacement.
         */
        $currentConfig = $model->getConfig();

        $currentSlack = is_array(
            $currentConfig['slack'] ?? null
        )
            ? $currentConfig['slack']
            : [];

        $webhook = trim(
            (string) (
                $currentSlack['webhook_url'] ?? ''
            )
        );

        $replacementWebhook = trim(
            (string) (
                $_POST['slack_webhook_url'] ?? ''
            )
        );

        if ($replacementWebhook !== '') {
            if (!$this->validSlackWebhook(
                $replacementWebhook
            )) {
                http_response_code(400);

                $this->error_page(
                    'Invalid Slack webhook URL.'
                );
            }

            $webhook = $replacementWebhook;
        }

        $slackEnabled =
            isset($_POST['slack_enabled']);

        $model->saveConfig([
            'version' => '1',

            'mail' => [
                'from_name' => $fromName,
                'from_email' => $fromEmail,
            ],

            'slack' => [
                'enabled' => $slackEnabled,
                'webhook_url' => $webhook,
            ],

            'topics' => array_values($topics),
        ]);
    }

    private function handleTicketAction(
        support_model $model,
        array $ticket,
        string $action
    ): void {
        $actor = $this->adminActor();

        switch ($action) {
            case 'status':
                $status = strtolower(
                    trim(
                        (string) (
                            $_POST['status'] ?? ''
                        )
                    )
                );

                if (!in_array(
                    $status,
                    [
                        'open',
                        'in_progress',
                        'waiting',
                        'resolved',
                        'closed',
                    ],
                    true
                )) {
                    http_response_code(400);

                    $this->error_page(
                        'Invalid Support ticket status.'
                    );
                }

                $model->setStatus(
                    $ticket,
                    $status,
                    $actor
                );

                $ticket['status'] = $status;

                $this->sendStatusNotification(
                    $model,
                    $ticket
                );
                break;

            case 'tier':
                $tier = strtolower(
                    trim(
                        (string) (
                            $_POST['tier'] ?? ''
                        )
                    )
                );

                if ($tier === 'dev') {
                    $tier = 't5';
                }

                if (!in_array(
                    $tier,
                    [
                        't1',
                        't2',
                        't3',
                        't4',
                        't5',
                    ],
                    true
                )) {
                    http_response_code(400);

                    $this->error_page(
                        'Invalid Support tier.'
                    );
                }

                $note = trim(
                    (string) (
                        $_POST['note'] ?? ''
                    )
                );

                $oldTier = strtolower(
                    (string) (
                        $ticket['tier'] ?? 't1'
                    )
                );

                if ($oldTier === 'dev') {
                    $oldTier = 't5';
                }

                $model->setTier(
                    $ticket,
                    $tier,
                    $actor,
                    $note !== ''
                        ? $note
                        : null
                );

                if ($tier !== $oldTier) {
                    $ticket['tier'] = $tier;

                    $this->sendTierNotification(
                        $model,
                        $ticket,
                        $actor,
                        $note !== ''
                            ? $note
                            : null
                    );
                }
                break;

            case 'assign':
                $assignee = trim(
                    (string) (
                        $_POST['assigned_to'] ?? ''
                    )
                );

                $model->assignTicket(
                    $ticket,
                    $assignee !== ''
                        ? $assignee
                        : null,
                    $actor
                );

                $ticket['assigned_to'] =
                    $assignee;

                $this->sendUpdateNotification(
                    $model,
                    $ticket,
                    'Assignment updated'
                );
                break;

            case 'reply':
                $message = trim(
                    (string) (
                        $_POST['message'] ?? ''
                    )
                );

                if ($message === '') {
                    http_response_code(400);

                    $this->error_page(
                        'A Support reply cannot be empty.'
                    );
                }

                $isInternal =
                    isset($_POST['is_internal']);

                $model->addReply(
                    (int) $ticket['id'],
                    $actor,
                    $message,
                    $isInternal
                );

                if (!$isInternal) {
                    $this->sendReplyNotification(
                        $model,
                        $ticket,
                        $actor,
                        $message
                    );
                }
                break;

            default:
                http_response_code(400);

                $this->error_page(
                    'Invalid Support ticket action.'
                );
        }
    }

    private function sendSlackTicketCreated(
        support_model $model,
        string $ticketId
    ): void {
        $config = $model->getConfig();

        $slack = is_array(
            $config['slack'] ?? null
        )
            ? $config['slack']
            : [];

        if (empty($slack['enabled'])) {
            return;
        }

        $webhook = trim(
            (string) (
                $slack['webhook_url'] ?? ''
            )
        );

        if ($webhook === '') {
            return;
        }

        try {
            require_once
                dirname(__DIR__) .
                '/libraries/slack_notifier.php';

            $notifier =
                new slack_notifier($webhook);

            $notifier->send(
                'SUPPORT: #' .
                $ticketId .
                ' Ticket Created ' .
                'https://www.stn-chain.org/support/ticket/' .
                rawurlencode($ticketId)
            );
        } catch (Throwable $exception) {
            error_log(
                'STNC Support Slack notification failed: ' .
                $exception->getMessage()
            );
        }
    }

    private function testSlack(
        support_model $model
    ): void {
        $config = $model->getConfig();

        $slack = is_array(
            $config['slack'] ?? null
        )
            ? $config['slack']
            : [];

        $webhook = trim(
            (string) (
                $slack['webhook_url'] ?? ''
            )
        );

        if ($webhook === '') {
            http_response_code(400);

            $this->error_page(
                'Slack webhook is not configured.'
            );
        }

        try {
            require_once
                dirname(__DIR__) .
                '/libraries/slack_notifier.php';

            $notifier =
                new slack_notifier($webhook);

            if (!$notifier->send(
                'SUPPORT: Slack notification test successful.'
            )) {
                throw new RuntimeException(
                    'Slack rejected the test notification.'
                );
            }
        } catch (Throwable $exception) {
            http_response_code(502);

            $this->error_page(
                'Slack test failed: ' .
                $exception->getMessage()
            );
        }
    }

    private function validSlackWebhook(
        string $url
    ): bool {
        if (
            filter_var(
                $url,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        return
            strtolower(
                (string) (
                    $parts['scheme'] ?? ''
                )
            ) === 'https' &&
            strtolower(
                (string) (
                    $parts['host'] ?? ''
                )
            ) === 'hooks.slack.com' &&
            str_starts_with(
                (string) (
                    $parts['path'] ?? ''
                ),
                '/services/'
            );
    }

    private function sendNewTicketNotifications(
        support_model $model,
        array $ticket
    ): void {
        $id =
            (string) $ticket['ticket_id'];

        $subject =
            (string) $ticket['subject'];

        $identity =
            $this->supportIdentity($model);

        if ($identity['email'] !== '') {
            $this->sendMail(
                $model,
                $identity['email'],
                $identity['name'],
                '[Support #' .
                    $id .
                    '] ' .
                    $subject,
                '<h2>New Support Ticket #' .
                    $this->escapeHtml($id) .
                    '</h2>' .
                    '<p><strong>From:</strong> ' .
                    $this->escapeHtml(
                        (string) $ticket['name']
                    ) .
                    ' (' .
                    $this->escapeHtml(
                        (string) $ticket['email']
                    ) .
                    ')</p>' .
                    '<p><strong>Type:</strong> ' .
                    $this->escapeHtml(
                        strtoupper(
                            (string) $ticket['type']
                        )
                    ) .
                    '</p>' .
                    '<p><strong>Subject:</strong> ' .
                    $this->escapeHtml($subject) .
                    '</p>' .
                    '<p>' .
                    $this->escapeHtml(
                        (string) $ticket['description']
                    ) .
                    '</p>' .
                    '<p><a href="' .
                    URLROOT .
                    '/admin/support/' .
                    rawurlencode($id) .
                    '">Open ticket in Admin</a></p>'
            );
        }

        $this->sendMail(
            $model,
            (string) $ticket['email'],
            (string) $ticket['name'],
            '[Support #' .
                $id .
                '] Ticket received',
            '<p>Hello ' .
                $this->escapeHtml(
                    (string) $ticket['name']
                ) .
                ',</p>' .
                '<p>Your Support ticket <strong>#' .
                $this->escapeHtml($id) .
                '</strong> has been received.</p>' .
                '<p><a href="' .
                URLROOT .
                '/support/ticket/' .
                rawurlencode($id) .
                '">View your Support ticket</a></p>'
        );
    }

    private function sendReplyNotification(
        support_model $model,
        array $ticket,
        string $actor,
        string $message
    ): void {
        $id =
            (string) $ticket['ticket_id'];

        $this->sendMail(
            $model,
            (string) $ticket['email'],
            (string) $ticket['name'],
            '[Support #' .
                $id .
                '] Support response',
            '<p>Hello ' .
                $this->escapeHtml(
                    (string) $ticket['name']
                ) .
                ',</p>' .
                '<p>' .
                $this->escapeHtml($actor) .
                ' responded to your Support ticket.</p>' .
                '<p>' .
                $this->escapeHtml($message) .
                '</p>' .
                '<p><a href="' .
                URLROOT .
                '/support/ticket/' .
                rawurlencode($id) .
                '">View ticket #' .
                $this->escapeHtml($id) .
                '</a></p>'
        );
    }

    private function sendTierNotification(
        support_model $model,
        array $ticket,
        string $actor,
        ?string $note
    ): void {
        $id =
            (string) $ticket['ticket_id'];

        $identity =
            $this->supportIdentity($model);

        if ($identity['email'] === '') {
            return;
        }

        $tier =
            $this->tierLabel(
                (string) $ticket['tier']
            );

        $body =
            '<p>Ticket <strong>#' .
            $this->escapeHtml($id) .
            '</strong> was moved to <strong>' .
            $this->escapeHtml($tier) .
            '</strong> by ' .
            $this->escapeHtml($actor) .
            '.</p>';

        if ($note !== null) {
            $body .=
                '<p><strong>Note:</strong> ' .
                $this->escapeHtml($note) .
                '</p>';
        }

        $body .=
            '<p><a href="' .
            URLROOT .
            '/admin/support/' .
            rawurlencode($id) .
            '">Open ticket in Admin</a></p>';

        $this->sendMail(
            $model,
            $identity['email'],
            $identity['name'],
            '[Support #' .
                $id .
                '] Tier changed to ' .
                $tier,
            $body
        );
    }

    private function sendStatusNotification(
        support_model $model,
        array $ticket
    ): void {
        $id =
            (string) $ticket['ticket_id'];

        $status = strtoupper(
            str_replace(
                '_',
                ' ',
                (string) $ticket['status']
            )
        );

        $this->sendMail(
            $model,
            (string) $ticket['email'],
            (string) $ticket['name'],
            '[Support #' .
                $id .
                '] ' .
                $status,
            '<p>Hello ' .
                $this->escapeHtml(
                    (string) $ticket['name']
                ) .
                ',</p>' .
                '<p>Your Support ticket <strong>#' .
                $this->escapeHtml($id) .
                '</strong> is now <strong>' .
                $this->escapeHtml($status) .
                '</strong>.</p>' .
                '<p><a href="' .
                URLROOT .
                '/support/ticket/' .
                rawurlencode($id) .
                '">View your Support ticket</a></p>'
        );
    }

    private function sendUpdateNotification(
        support_model $model,
        array $ticket,
        string $update
    ): void {
        $id =
            (string) $ticket['ticket_id'];

        $this->sendMail(
            $model,
            (string) $ticket['email'],
            (string) $ticket['name'],
            '[Support #' .
                $id .
                '] ' .
                $update,
            '<p>Hello ' .
                $this->escapeHtml(
                    (string) $ticket['name']
                ) .
                ',</p>' .
                '<p>' .
                $this->escapeHtml($update) .
                '.</p>' .
                '<p><a href="' .
                URLROOT .
                '/support/ticket/' .
                rawurlencode($id) .
                '">View your Support ticket</a></p>'
        );
    }

    private function sendMail(
        support_model $model,
        string $address,
        string $name,
        string $subject,
        string $body
    ): void {
        try {
            $mailObj = new mailer();
            $mail = $mailObj->create();

            $identity =
                $this->supportIdentity($model);

            if ($identity['email'] !== '') {
                $mail->setFrom(
                    $identity['email'],
                    $identity['name']
                );
            }

            $mail->addAddress(
                $address,
                $name
            );

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
        } catch (Throwable $exception) {
            error_log(
                'STNC Support mail notification failed: ' .
                $exception->getMessage()
            );
        }
    }

    private function supportIdentity(
        support_model $model
    ): array {
        $config = $model->getConfig();

        $mail = is_array(
            $config['mail'] ?? null
        )
            ? $config['mail']
            : [];

        $site = $this->siteIdentity();

        $name = trim(
            (string) (
                $mail['from_name'] ?? ''
            )
        );

        $email = trim(
            (string) (
                $mail['from_email'] ?? ''
            )
        );

        if ($name === '') {
            $name =
                $site['name'] !== ''
                    ? $site['name']
                    : 'Support';
        }

        if ($email === '') {
            $email = $site['email'];
        }

        return [
            'name' => $name,
            'email' => $email,
        ];
    }

    private function siteIdentity(): array
    {
        global $SITE;

        $site = is_array($SITE ?? null)
            ? $SITE
            : [];

        $name = trim(
            (string) (
                $site['name'] ??
                $site['site_name'] ??
                $site['title'] ??
                ''
            )
        );

        $email = trim(
            (string) (
                $site['email'] ??
                $site['site_email'] ??
                $site['contact_email'] ??
                ''
            )
        );

        return [
            'name' => $name,
            'email' =>
                filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                ) !== false
                    ? $email
                    : '',
        ];
    }

    private function adminActor(): string
    {
        if (
            isset($_SESSION['username']) &&
            trim(
                (string) $_SESSION['username']
            ) !== ''
        ) {
            return trim(
                (string) $_SESSION['username']
            );
        }

        if (
            isset($_SESSION['user']['username']) &&
            trim(
                (string) $_SESSION['user']['username']
            ) !== ''
        ) {
            return trim(
                (string) $_SESSION['user']['username']
            );
        }

        return 'Administrator';
    }

    private function tierLabel(
        string $tier
    ): string {
        $tier = strtolower($tier);

        if ($tier === 'dev') {
            $tier = 't5';
        }

        return strtoupper($tier);
    }

    private function escapeHtml(
        string $value
    ): string {
        return htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}