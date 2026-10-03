<?php

/* [AI:GPT-5.6 Sol | 2026-10-02 UTC] */

/**
 * Administrative Support ticket view.
 *
 * @var array<string, mixed> $data
 */

if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$ticket = $data['ticket'] ?? null;
$replies = $data['replies'] ?? [];
$events = $data['events'] ?? [];
?>
<main class="container py-4">
<?php if (!is_array($ticket)): ?>
<h1>Ticket Not Found</h1><p><a href="/admin/support">&larr; Support Queue</a></p>
<?php else: ?>
<p><a href="/admin/support">&larr; Support Queue</a></p>
<h1>#<?= $escape($ticket['ticket_id']); ?> <?= $escape($ticket['subject']); ?></h1>
<div class="row g-4"><div class="col-lg-8">
<section class="card mb-4"><div class="card-body"><h2 class="h5">Ticket</h2><p><?= nl2br($escape($ticket['description'])); ?></p><dl class="row">
<dt class="col-sm-3">Submitted by</dt><dd class="col-sm-9"><?= $escape($ticket['name']); ?></dd>
<dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= $escape($ticket['email']); ?></dd>
<dt class="col-sm-3">Type</dt><dd class="col-sm-9"><?= $escape($ticket['type']); ?></dd>
<dt class="col-sm-3">Reported</dt><dd class="col-sm-9"><?= $escape($ticket['created_at']); ?></dd></dl></div></section>
<section class="card mb-4"><div class="card-body"><h2 class="h5">Reply / Internal Note</h2><form method="post"><?= $this->csrf_field(); ?><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?= $escape($ticket['ticket_id']); ?>"><div class="mb-3"><textarea class="form-control" name="message" rows="6" required></textarea></div><div class="form-check mb-3"><input id="support-internal" class="form-check-input" type="checkbox" name="is_internal" value="1"><label class="form-check-label" for="support-internal">Internal note</label></div><button type="submit" class="btn btn-primary">Add Reply</button></form></div></section>
<?php if ($replies !== []): ?><section class="card mb-4"><div class="card-body"><h2 class="h5">Conversation</h2><?php foreach ($replies as $reply): ?><article class="border-bottom py-3"><p class="mb-1"><strong><?= $escape($reply['author']); ?></strong><?php if (!empty($reply['is_internal'])): ?> <span class="badge text-bg-warning">Internal</span><?php endif; ?></p><p class="text-muted small"><?= $escape($reply['created_at']); ?></p><p><?= nl2br($escape($reply['message'])); ?></p></article><?php endforeach; ?></div></section><?php endif; ?>
</div><div class="col-lg-4">
<section class="card mb-4"><div class="card-body"><h2 class="h5">Status</h2><form method="post"><?= $this->csrf_field(); ?><input type="hidden" name="action" value="status"><input type="hidden" name="ticket_id" value="<?= $escape($ticket['ticket_id']); ?>"><select class="form-select mb-3" name="status"><?php $statuses=['open'=>'Open','in_progress'=>'In Progress','waiting'=>'Waiting','resolved'=>'Resolved','closed'=>'Closed']; foreach($statuses as $value=>$label): ?><option value="<?= $escape($value); ?>" <?= $ticket['status']===$value?'selected':''; ?>><?= $escape($label); ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-secondary">Update Status</button></form></div></section>
<section class="card mb-4"><div class="card-body"><h2 class="h5">Tier / Escalation</h2><form method="post"><?= $this->csrf_field(); ?><input type="hidden" name="action" value="tier"><input type="hidden" name="ticket_id" value="<?= $escape($ticket['ticket_id']); ?>"><select class="form-select mb-3" name="tier"><?php $tiers=['t1'=>'Tier 1','t2'=>'Tier 2','t3'=>'Tier 3','t4'=>'Tier 4','t5'=>'Tier 5 — Dev']; $currentTier=(string)($ticket['tier']??'t1'); if($currentTier==='dev'){$currentTier='t5';} foreach($tiers as $value=>$label): ?><option value="<?= $escape($value); ?>" <?= $currentTier===$value?'selected':''; ?>><?= $escape($label); ?></option><?php endforeach; ?></select><div class="mb-3"><label class="form-label" for="support-tier-note">Reason / Note</label><textarea id="support-tier-note" class="form-control" name="note" rows="3"></textarea></div><button type="submit" class="btn btn-secondary">Update Tier</button></form></div></section>
<section class="card mb-4"><div class="card-body"><h2 class="h5">Assignment</h2><form method="post"><?= $this->csrf_field(); ?><input type="hidden" name="action" value="assign"><input type="hidden" name="ticket_id" value="<?= $escape($ticket['ticket_id']); ?>"><input class="form-control mb-3" type="text" name="assigned_to" maxlength="100" value="<?= $escape($ticket['assigned_to']??''); ?>" placeholder="Initials or team"><button type="submit" class="btn btn-secondary">Assign</button></form></div></section>
<?php if($events!==[]): ?><section class="card"><div class="card-body"><h2 class="h5">History</h2><?php foreach($events as $event): ?><div class="border-bottom py-2"><strong><?= $escape($event['event_type']); ?></strong><?php if(($event['from_value']??null)!==null||($event['to_value']??null)!==null): ?><div><?= $escape($event['from_value']??'—'); ?> &rarr; <?= $escape($event['to_value']??'—'); ?></div><?php endif; ?><?php if(($event['note']??'')!==''): ?><div><?= nl2br($escape($event['note'])); ?></div><?php endif; ?><small class="text-muted"><?= $escape($event['actor']??'System'); ?> · <?= $escape($event['created_at']); ?></small></div><?php endforeach; ?></div></section><?php endif; ?>
</div></div>
<?php endif; ?>
</main>
<?php if (!theme::render('foot', get_defined_vars())) { require APPROOT . '/views/inc/foot.php'; }
/* [End AI:GPT-5.6 Sol] */
