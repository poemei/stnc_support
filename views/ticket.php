<?php

/* [AI:GPT-5.6 Sol | 2026-09-07 18:30:00 UTC] */

/**
 * Public Support ticket view.
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

$ticket = $data['ticket'] ?? null;
$replies = $data['replies'] ?? [];

?>

<main class="container py-5">
    <div style="max-width: 860px; margin: 0 auto;">

        <?php if (!is_array($ticket)): ?>

            <h1>Ticket Not Found</h1>

            <p>
                The requested support ticket could not be found.
            </p>

            <p>
                <a href="/support">Return to Support</a>
            </p>

        <?php else: ?>

            <p>
                <a href="/support">&larr; Support</a>
            </p>

            <h1>
                #<?= $escape($ticket['ticket_id']); ?>
                <?= $escape($ticket['subject']); ?>
            </h1>

            <p>
                <strong>
                    <?= $escape(strtoupper($ticket['status'])); ?>
                </strong>
            </p>

            <p>
                <?= nl2br($escape($ticket['description'])); ?>
            </p>

            <p>
                Reported on:
                <?= $escape($ticket['created_at']); ?>
            </p>

            <?php if ($replies !== []): ?>
                <hr>

                <h2>Replies</h2>

                <?php foreach ($replies as $reply): ?>
                    <article class="mb-4">
                        <strong>
                            <?= $escape($reply['author']); ?>
                        </strong>

                        <small>
                            <?= $escape($reply['created_at']); ?>
                        </small>

                        <p>
                            <?= nl2br($escape($reply['message'])); ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</main>

<?php

if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}

/* [End AI:GPT-5.6 Sol] */