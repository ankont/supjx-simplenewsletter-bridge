<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
?>
<div class="newsletterbridge">
<h1><?= Text::_('COM_NEWSLETTERBRIDGE_PREVIEW') ?>: <?= $this->escape($this->item->title) ?></h1>
<p><?= Text::_('COM_NEWSLETTERBRIDGE_PREVIEW_HELP') ?></p>
<a class="btn btn-secondary" href="<?= $this->url() ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_BACK') ?></a>
<iframe class="nb-preview" title="<?= $this->escape(Text::_('COM_NEWSLETTERBRIDGE_PREVIEW')) ?>" sandbox="" referrerpolicy="no-referrer" srcdoc="<?= $this->escape($this->body) ?>"></iframe>
</div>
