<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Model;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use SuperSoft\Component\Newsletterbridge\Site\Service\Bridge;

class NewslettersModel extends ListModel
{
    protected function populateState($ordering = 'a.id', $direction = 'DESC')
    {
        $app = Factory::getApplication();
        $input = $app->getInput();
        $limit = max(5, min(100, $input->getInt('limit', 20)));
        $this->setState('list.limit', $limit);
        $this->setState('list.start', max(0, $input->getInt('limitstart', 0)));
        $this->setState('filter.search', substr(trim($input->getString('search', '')), 0, 100));
    }
    protected function getStoreId($id = '')
    {
        $id .= ':' . (int) Factory::getApplication()->getIdentity()->id . ':' . $this->getState('filter.search');
        return parent::getStoreId($id);
    }
    protected function getListQuery()
    {
        $bridge = new Bridge();
        $db = $this->getDatabase();
        $q = $db->getQuery(true)->select($db->quoteName(['a.id','a.title','a.subject','a.published','a.created_by','a.modified','a.checked_out']))
            ->from($db->quoteName('#__simplenewsletter_templates', 'a'))
            ->whereIn($db->quoteName('a.published'), [0, 1]);
        if (!$bridge->policy->allows('core.edit')) {
            $q->where($db->quoteName('a.created_by') . ' = ' . (int) Factory::getApplication()->getIdentity()->id);
            if (!$bridge->policy->allows('core.edit.own')) { $q->where('1 = 0'); }
        }
        $search = (string) $this->getState('filter.search');
        if ($search !== '') {
            $like = $db->quote('%' . $db->escape($search, true) . '%', false);
            $q->where('(' . $db->quoteName('a.title') . ' LIKE ' . $like . ' OR ' . $db->quoteName('a.subject') . ' LIKE ' . $like . ')');
        }
        return $q->order($db->quoteName('a.id') . ' DESC');
    }
}
