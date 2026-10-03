<?php

/* [AI:GPT-5.6 Sol | 2026-10-02 UTC] */

/**
 * Public Support module view.
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

$available = (bool) ($data['available'] ?? false);
$topics = is_array($data['topics'] ?? null) ? $data['topics'] : [];
?>

<main class="container py-5">
    <div style="max-width: 760px; margin: 0 auto;">
        <h1>Support</h1>

        <?php if (!$available): ?>
            <div class="alert alert-warning">
                Support is currently unavailable.
            </div>
        <?php else: ?>
            <p>
                Need help? Open a support ticket below.
            </p>

            <?php if (($data['error'] ?? null) !== null): ?>
                <div class="alert alert-danger">
                    <?= $escape($data['error']); ?>
                </div>
            <?php endif; ?>

            <?php if ($topics === []): ?>
                <div class="alert alert-warning">
                    Support is not currently accepting new tickets.
                </div>
            <?php else: ?>
                <form method="post" action="/support">
                    <?= $this->csrf_field(); ?>

                    <div class="mb-3">
                        <label for="support-name" class="form-label">Name</label>
                        <input
                            id="support-name"
                            class="form-control"
                            type="text"
                            name="name"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="support-email" class="form-label">Email</label>
                        <input
                            id="support-email"
                            class="form-control"
                            type="email"
                            name="email"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="support-type" class="form-label">Type</label>
                        <select
                            id="support-type"
                            class="form-select"
                            name="type"
                            required
                        >
                            <?php foreach ($topics as $topic): ?>
                                <?php
                                if (!is_array($topic)) {
                                    continue;
                                }

                                $value = trim((string) ($topic['value'] ?? ''));
                                $label = trim((string) ($topic['label'] ?? ''));

                                if ($value === '' || $label === '') {
                                    continue;
                                }
                                ?>
                                <option value="<?= $escape($value); ?>">
                                    <?= $escape($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="support-subject" class="form-label">Subject</label>
                        <input
                            id="support-subject"
                            class="form-control"
                            type="text"
                            name="subject"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="support-description" class="form-label">
                            What happened?
                        </label>
                        <textarea
                            id="support-description"
                            class="form-control"
                            name="description"
                            rows="8"
                            required
                        ></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Open Ticket
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}

/* [End AI:GPT-5.6 Sol] */
