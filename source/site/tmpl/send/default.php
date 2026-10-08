<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
?>
<div class="newsletterbridge" data-nb-send data-id="<?= (int) $this->item->id ?>">
<h1><?= Text::_('COM_NEWSLETTERBRIDGE_SEND') ?>: <?= $this->escape($this->item->title) ?></h1>
<a class="btn btn-secondary" href="<?= $this->url() ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_BACK') ?></a>
<a class="btn btn-secondary" href="<?= $this->url('preview', (int) $this->item->id) ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_PREVIEW') ?></a>
<p><?= Text::_('COM_NEWSLETTERBRIDGE_SEND_HELP') ?></p>
<form data-nb-test>
<label for="nb-email"><?= Text::_('COM_NEWSLETTERBRIDGE_TEST_EMAIL') ?></label>
<input type="email" id="nb-email" required maxlength="190" autocomplete="off">
<button type="submit" class="btn btn-secondary"><?= Text::_('COM_NEWSLETTERBRIDGE_TEST') ?></button>
</form>
<?php if ((int) $this->item->published === 1) : ?>
<form data-nb-start>
<label><input type="checkbox" name="confirmed" value="1" required> <?= Text::_('COM_NEWSLETTERBRIDGE_CONFIRM_SEND') ?></label>
<button type="submit" class="btn btn-primary"><?= Text::_('COM_NEWSLETTERBRIDGE_START') ?></button>
</form>
<?php else : ?><p><?= Text::_('COM_NEWSLETTERBRIDGE_PUBLISH_FIRST') ?></p><?php endif; ?>
<p data-nb-message role="status" aria-live="polite"></p>
<progress data-nb-progress max="1" value="0" aria-label="<?= $this->escape(Text::_('COM_NEWSLETTERBRIDGE_PROGRESS')) ?>"></progress>
<p data-nb-counts aria-live="polite"></p>
<button type="button" class="btn btn-secondary" data-nb-pause hidden><?= Text::_('COM_NEWSLETTERBRIDGE_PAUSE') ?></button>
<div class="nb-table"><table><caption><?= Text::_('COM_NEWSLETTERBRIDGE_JOBS') ?></caption><thead><tr>
<th scope="col">ID</th><th scope="col"><?= Text::_('COM_NEWSLETTERBRIDGE_CREATED') ?></th><th scope="col"><?= Text::_('JSTATUS') ?></th><th scope="col"><?= Text::_('COM_NEWSLETTERBRIDGE_TOTAL') ?></th><th scope="col"><?= Text::_('COM_NEWSLETTERBRIDGE_SENT') ?></th><th scope="col"><?= Text::_('COM_NEWSLETTERBRIDGE_FAILED') ?></th><th scope="col"><?= Text::_('COM_NEWSLETTERBRIDGE_ACTIONS') ?></th>
</tr></thead><tbody data-nb-jobs>
<?php foreach ($this->jobs as $job) : ?><tr data-job="<?= $job['jobId'] ?>">
<td><?= $job['jobId'] ?></td><td><?= $this->escape($job['created']) ?></td><td data-col="state"><?= Text::_('COM_NEWSLETTERBRIDGE_JOB_STATE_' . $job['state']) ?></td>
<td data-col="total"><?= $job['total'] ?></td><td data-col="sent"><?= $job['sent'] ?></td><td data-col="failed"><?= $job['failed'] ?></td>
<td><?php if (!$job['done']) : ?><button type="button" class="btn btn-secondary" data-nb-resume="<?= $job['jobId'] ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_RESUME') ?></button>
<button type="button" class="btn btn-secondary" data-nb-cancel="<?= $job['jobId'] ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_CANCEL_JOB') ?></button><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<form action="<?= $this->url('send', (int) $this->item->id) ?>" method="post" id="adminForm" name="adminForm"><?= $this->pagination->getPagesLinks() ?></form>
<template data-nb-active-actions><button type="button" class="btn btn-secondary" data-nb-resume><?= Text::_('COM_NEWSLETTERBRIDGE_RESUME') ?></button><button type="button" class="btn btn-secondary" data-nb-cancel><?= Text::_('COM_NEWSLETTERBRIDGE_CANCEL_JOB') ?></button></template>
</div>
<?php
$app = \Joomla\CMS\Factory::getApplication();
$labels = [];
foreach (['TOTAL','SENT','FAILED','PRESET_CONFIRM','CANCEL_CONFIRM','NETWORK_ERROR','PAUSED','DONE','SENDING','TEST_SENT','BUSY'] as $key) { $labels[strtolower($key)] = Text::_('COM_NEWSLETTERBRIDGE_' . $key); }
for ($i = 0; $i <= 3; $i++) { $labels['state' . $i] = Text::_('COM_NEWSLETTERBRIDGE_JOB_STATE_' . $i); }
$app->getDocument()->addScriptOptions('com_newsletterbridge.labels', $labels);
?>
