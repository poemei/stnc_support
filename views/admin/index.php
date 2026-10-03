<?php

/* [AI:GPT-5.6 Sol | 2026-10-02 UTC] */

/**
 * Support administration queue, configuration, and lifecycle controls.
 *
 * @var array<string, mixed> $data
 */

if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}

$escape = static fn ($value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES,
    'UTF-8'
);

$databaseState = (string) ($data['database_state'] ?? 'missing');
$tickets = $data['tickets'] ?? [];

$config = is_array($data['config'] ?? null)
    ? $data['config']
    : [];

$mailConfig = is_array($config['mail'] ?? null)
    ? $config['mail']
    : [];

$slackConfig = is_array($config['slack'] ?? null)
    ? $config['slack']
    : [];

$topics = is_array($config['topics'] ?? null)
    ? $config['topics']
    : [];

$slackEnabled = !empty($slackConfig['enabled']);

$slackConfigured = trim(
    (string) ($slackConfig['webhook_url'] ?? '')
) !== '';

$siteFromName = (string) ($data['site_from_name'] ?? '');
$siteFromEmail = (string) ($data['site_from_email'] ?? '');

?>

<main class="container py-4">
    <h1>Support</h1>

    <?php if (isset($_GET['installed'])): ?>
        <div class="alert alert-success">
            Support database installed.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            Support data deleted.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['configured'])): ?>
        <div class="alert alert-success">
            Support configuration saved.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['slack_tested'])): ?>
        <div class="alert alert-success">
            Slack notification test sent.
        </div>
    <?php endif; ?>

    <section class="border rounded p-4 mb-4">
        <h2 class="h4">Configuration</h2>

        <p>
            Support inherits the site identity when an override is blank.
            Use overrides when Support needs its own sender identity, such as
            support@example.com.
        </p>

        <form method="post" action="/admin/support">
            <?= $this->csrf_field(); ?>

            <input
                type="hidden"
                name="action"
                value="save_config"
            >

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label
                        class="form-label"
                        for="support-from-name"
                    >
                        From Name
                    </label>

                    <input
                        id="support-from-name"
                        class="form-control"
                        type="text"
                        name="from_name"
                        maxlength="150"
                        value="<?= $escape(
                            $mailConfig['from_name'] ?? ''
                        ); ?>"
                    >

                    <div class="form-text">
                        Inherited:
                        <?= $escape(
                            $siteFromName !== ''
                                ? $siteFromName
                                : 'Not supplied by site.json'
                        ); ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label
                        class="form-label"
                        for="support-from-email"
                    >
                        From Email
                    </label>

                    <input
                        id="support-from-email"
                        class="form-control"
                        type="email"
                        name="from_email"
                        maxlength="254"
                        value="<?= $escape(
                            $mailConfig['from_email'] ?? ''
                        ); ?>"
                    >

                    <div class="form-text">
                        Inherited:
                        <?= $escape(
                            $siteFromEmail !== ''
                                ? $siteFromEmail
                                : 'Not supplied by site.json'
                        ); ?>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <h3 class="h5">Slack Notifications</h3>

            <p class="text-muted">
                Notify the STN Chain Support channel when a new
                Support ticket is created.
            </p>

            <div class="form-check mb-3">
                <input
                    id="support-slack-enabled"
                    class="form-check-input"
                    type="checkbox"
                    name="slack_enabled"
                    value="1"
                    <?= $slackEnabled ? 'checked' : ''; ?>
                >

                <label
                    class="form-check-label"
                    for="support-slack-enabled"
                >
                    Enable Slack ticket notifications
                </label>
            </div>

            <div class="mb-4">
                <label
                    class="form-label"
                    for="support-slack-webhook"
                >
                    Slack Webhook
                </label>

                <input
                    id="support-slack-webhook"
                    class="form-control"
                    type="password"
                    name="slack_webhook_url"
                    maxlength="2048"
                    value=""
                    autocomplete="new-password"
                    placeholder="<?= $slackConfigured
                        ? 'Webhook configured — enter a new URL to replace it'
                        : 'https://hooks.slack.com/services/...'; ?>"
                >

                <div class="form-text">
                    Status:
                    <strong>
                        <?= $slackConfigured
                            ? 'Configured'
                            : 'Not configured'; ?>
                    </strong>.
                    The stored webhook is never displayed.
                </div>
            </div>

            <hr class="my-4">

            <h3 class="h5">Topics</h3>

            <p class="text-muted">
                Topic values are stable ticket identifiers.
                Labels are what users see.
                Disable a topic instead of renaming its value
                when historical tickets use it.
            </p>

            <div class="table-responsive mb-3">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Value</th>
                            <th>Label</th>
                            <th>Enabled</th>
                            <th>Remove</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($topics as $index => $topic): ?>
                        <?php
                        if (!is_array($topic)) {
                            continue;
                        }
                        ?>

                        <tr>
                            <td>
                                <input
                                    class="form-control"
                                    type="text"
                                    name="topics[<?= (int) $index; ?>][value]"
                                    value="<?= $escape(
                                        $topic['value'] ?? ''
                                    ); ?>"
                                    maxlength="64"
                                >
                            </td>

                            <td>
                                <input
                                    class="form-control"
                                    type="text"
                                    name="topics[<?= (int) $index; ?>][label]"
                                    value="<?= $escape(
                                        $topic['label'] ?? ''
                                    ); ?>"
                                    maxlength="100"
                                >
                            </td>

                            <td>
                                <input
                                    type="hidden"
                                    name="topics[<?= (int) $index; ?>][enabled]"
                                    value="0"
                                >

                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="topics[<?= (int) $index; ?>][enabled]"
                                    value="1"
                                    <?= !empty($topic['enabled'])
                                        ? 'checked'
                                        : ''; ?>
                                >
                            </td>

                            <td>
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="topics[<?= (int) $index; ?>][remove]"
                                    value="1"
                                >
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <tr>
                        <td>
                            <input
                                class="form-control"
                                type="text"
                                name="new_topic_value"
                                maxlength="64"
                                placeholder="new_topic"
                            >
                        </td>

                        <td>
                            <input
                                class="form-control"
                                type="text"
                                name="new_topic_label"
                                maxlength="100"
                                placeholder="New Topic"
                            >
                        </td>

                        <td>
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="new_topic_enabled"
                                value="1"
                                checked
                            >
                        </td>

                        <td>
                            <span class="text-muted">
                                New
                            </span>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Configuration
            </button>
        </form>

        <?php if ($slackConfigured): ?>
            <form
                method="post"
                action="/admin/support"
                class="mt-3"
            >
                <?= $this->csrf_field(); ?>

                <input
                    type="hidden"
                    name="action"
                    value="test_slack"
                >

                <button
                    type="submit"
                    class="btn btn-secondary"
                >
                    Test Slack Notification
                </button>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($databaseState !== 'ready'): ?>
        <div class="alert alert-warning">
            <strong>Database not installed.</strong>
            Support cannot accept tickets until its database
            tables have been created.
        </div>

        <form
            method="post"
            action="/admin/support"
            class="mb-4"
        >
            <?= $this->csrf_field(); ?>

            <input
                type="hidden"
                name="action"
                value="install_sql"
            >

            <button
                type="submit"
                class="btn btn-primary"
            >
                Install SQL
            </button>
        </form>
    <?php else: ?>
        <p>
            Manage support tickets, assignment, support tier,
            escalation, and lifecycle.
        </p>

        <?php if ($tickets === []): ?>
            <div class="alert alert-secondary">
                No support tickets have been submitted.
            </div>
        <?php else: ?>
            <div class="table-responsive mb-5">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Tier</th>
                            <th>Assigned</th>
                            <th>Reported</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php
                        $tier = strtolower(
                            (string) (
                                $ticket['tier'] ?? 't1'
                            )
                        );

                        if ($tier === 'dev') {
                            $tier = 't5';
                        }

                        $tierLabels = [
                            't1' => 'Tier 1',
                            't2' => 'Tier 2',
                            't3' => 'Tier 3',
                            't4' => 'Tier 4',
                            't5' => 'Tier 5 — Dev',
                        ];
                        ?>

                        <tr>
                            <td>
                                <a href="/admin/support/<?= $escape(
                                    $ticket['ticket_id']
                                ); ?>">
                                    #<?= $escape(
                                        $ticket['ticket_id']
                                    ); ?>
                                </a>
                            </td>

                            <td>
                                <?= $escape(
                                    ucfirst(
                                        (string) $ticket['type']
                                    )
                                ); ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $ticket['subject']
                                ); ?>
                            </td>

                            <td>
                                <?= $escape(
                                    strtoupper(
                                        str_replace(
                                            '_',
                                            ' ',
                                            (string) $ticket['status']
                                        )
                                    )
                                ); ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $tierLabels[$tier] ??
                                    strtoupper($tier)
                                ); ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $ticket['assigned_to'] ??
                                    'Unassigned'
                                ); ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $ticket['created_at']
                                ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <section class="border rounded p-4 mb-4">
            <h2 class="h4">Data</h2>

            <p>
                Delete all Support tickets, replies, and
                lifecycle history while preserving the
                Support database schema.
            </p>

            <form
                method="post"
                action="/admin/support"
                onsubmit="return confirm('Delete all Support data? This cannot be undone.');"
            >
                <?= $this->csrf_field(); ?>

                <input
                    type="hidden"
                    name="action"
                    value="delete_data"
                >

                <button
                    type="submit"
                    class="btn btn-warning"
                >
                    Delete Data
                </button>
            </form>
        </section>
    <?php endif; ?>

    <section class="border rounded p-4">
        <h2 class="h4">Lifecycle</h2>

        <p>
            Nuke removes the Support module through the
            ChAoS Core module lifecycle.
        </p>

        <form
            method="post"
            action="/admin/uninstall"
            onsubmit="return confirm('Nuke the Support module? This cannot be undone.');"
        >
            <?= $this->csrf_field(); ?>

            <input
                type="hidden"
                name="module"
                value="support"
            >

            <button
                type="submit"
                class="btn btn-danger"
            >
                Nuke
            </button>
        </form>
    </section>
</main>

<?php

if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}

/* [End AI:GPT-5.6 Sol] */