<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
?>
<article class="newsletterbridge">
<h1><?= $this->escape($this->item->title) ?></h1>
<p><?= $this->escape($this->item->sent_at ? HTMLHelper::_('date', $this->item->sent_at, Text::_('DATE_FORMAT_LC3')) : '') ?></p>
<p><?= Text::_('COM_NEWSLETTERBRIDGE_PUBLIC_COPY') ?></p>
<div class="nb-public-body"><?= $this->item->body /* Strict PublicContent allowlist on both write and read. */ ?></div>
<p><a href="<?= $this->back ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_BACK') ?></a></p>
</article>
