<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
$preview = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src &apos;none&apos;; img-src &apos;self&apos;; style-src &apos;unsafe-inline&apos;; base-uri &apos;none&apos;; form-action &apos;none&apos;"></head><body>' . $this->item->body . '</body></html>';
?>
<div class="newsletterbridge" data-nb-publication>
<h1><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_EDIT') ?></h1>
<p><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_HELP') ?></p>
<p class="alert alert-info"><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_PRIVACY') ?></p>
<p><?= Text::_($this->item->published ? 'COM_NEWSLETTERBRIDGE_ARCHIVE_PUBLIC' : 'COM_NEWSLETTERBRIDGE_ARCHIVE_PRIVATE') ?></p>
<p><a href="<?= $this->url('publications', (int) $this->item->template_id) ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_BACK') ?></a></p>
<form action="<?= $this->url('publication', (int) $this->item->job_id) ?>" method="post">
<?= $this->form->renderField('title') ?><?= $this->form->renderField('subject') ?><?= $this->form->renderField('body') ?>
<input type="hidden" name="id" value="<?= (int) $this->item->job_id ?>"><input type="hidden" name="revision" value="<?= (int) $this->item->revision ?>">
<?= HTMLHelper::_('form.token') ?>
<p><label><input type="checkbox" name="reviewed" value="1"> <?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_REVIEW') ?></label></p>
<div class="nb-actions">
<button class="btn btn-secondary" type="submit" name="task" value="publication.save" formnovalidate><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_DRAFT') ?></button>
<button class="btn btn-primary" type="submit" name="task" value="publication.publish"><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_PUBLISH') ?></button>
<?php if ($this->item->published) : ?><button class="btn btn-danger" type="submit" name="task" value="publication.withdraw" formnovalidate><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_WITHDRAW') ?></button><?php endif; ?>
</div>
<p><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_DRAFT_HELP') ?></p>
</form>
<h2><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_PREVIEW') ?></h2>
<iframe class="nb-preview" title="<?= $this->escape(Text::_('COM_NEWSLETTERBRIDGE_PREVIEW')) ?>" sandbox="" referrerpolicy="no-referrer" srcdoc="<?= $this->escape($preview) ?>"></iframe>
</div>
