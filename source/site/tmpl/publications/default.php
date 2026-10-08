<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
?>
<div class="newsletterbridge">
<h1><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_MANAGE') ?>: <?= $this->escape($this->item->title) ?></h1>
<p><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_HELP') ?></p>
<p><a href="<?= $this->url() ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_BACK') ?></a></p>
<?php if (!$this->items) : ?><p><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_NO_JOBS') ?></p><?php else : ?>
<div class="nb-table"><table><thead><tr><th><?= Text::_('COM_NEWSLETTERBRIDGE_SUBJECT') ?></th><th><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_SENT_AT') ?></th><th><?= Text::_('JSTATUS') ?></th><th><?= Text::_('COM_NEWSLETTERBRIDGE_ACTIONS') ?></th></tr></thead><tbody>
<?php foreach ($this->items as $item) : ?><tr><td><?= $this->escape($item->subject) ?></td><td><?= $this->escape($item->finished ? HTMLHelper::_('date', $item->finished, Text::_('DATE_FORMAT_LC3')) : '') ?></td><td><?= Text::_($item->published ? 'COM_NEWSLETTERBRIDGE_ARCHIVE_PUBLIC' : 'COM_NEWSLETTERBRIDGE_ARCHIVE_PRIVATE') ?></td><td><a class="btn btn-secondary" href="<?= $this->url('publication', (int) $item->id) ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_EDIT') ?></a></td></tr><?php endforeach; ?>
</tbody></table></div>
<form action="<?= $this->url('publications', (int) $this->item->id) ?>" method="post" id="adminForm" name="adminForm"><?= $this->pagination->getPagesLinks() ?></form>
<?php endif; ?>
</div>
