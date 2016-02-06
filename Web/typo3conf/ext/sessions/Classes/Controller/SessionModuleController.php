<?php
namespace TYPO3\Sessions\Controller;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;

/**
 * Class SessionModuleController
 * @package TYPO3\Sessions\Controller
 */
class SessionModuleController extends ActionController
{

    /**
     * @var string
     */
    protected $resourceArgumentName = 'session';

    /**
     * @var BackendTemplateView
     */
    protected $view;

    /**
     * @var \TYPO3\Sessions\Domain\Repository\ScheduledSessionRepository
     * @inject
     */
    protected $sessionRepository;

    /**
     * @var \TYPO3\Sessions\Service\CreateTimetableService
     * @inject
     */
    protected $createTimetableService;

    /**
     * @var \TYPO3\Sessions\Domain\Repository\RoomRepository
     * @inject
     */
    protected $roomRepository;

    /**
     * BackendTemplateView Container
     *
     * @var BackendTemplateView
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /**
     * Blacklist for actions which don't want/need the menu
     * @var array
     */
    protected $actionsWithoutMenu = [];

    /**
     * @var \TYPO3\Sessions\Planning\Utility
     */
    protected $utility;

    /**
     * Initializes the module view.
     *
     * @param ViewInterface $view The view
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        $extPath = $this->getRelativeExtensionPath().'Resources/Public/CSS/';
        // Skip, if view is initialized in non-backend context
        if (!($view instanceof BackendTemplateView)) {
            return;
        }

        parent::initializeView($view);
        if($this->actionMethodName === 'indexAction') {
            $view->getModuleTemplate()->getPageRenderer()->addCssFile($extPath.'fullcalendar.min.css');
            $view->getModuleTemplate()->getPageRenderer()->addCssFile($extPath.'scheduler.min.css');
            $view->getModuleTemplate()->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Sessions/fullcalendar');
            $view->getModuleTemplate()->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Sessions/scheduler');
            $view->getModuleTemplate()->getPageRenderer()->addRequireJsConfiguration([
                'shim'  => [
                    'TYPO3/CMS/Sessions/scheduler' => [
                        'deps'  =>  ['TYPO3/CMS/Sessions/fullcalendar']
                    ]
                ]
            ]);
        }

        if($this->actionMethodName === 'manageAction') {
            $view->getModuleTemplate()->getPageRenderer()->addCssFile($extPath.'manage.css');
        }

        if(!in_array($this->actionMethodName, $this->actionsWithoutMenu)) {
            $this->generateModuleMenu();
            $this->generateModuleButtons();
        }
    }

    /**
     * @return string
     */
    protected function getRelativeExtensionPath() {
        return ExtensionManagementUtility::extRelPath('sessions');
    }

    /**
     * Generates module menu.
     */
    protected function generateModuleMenu()
    {
        $menuItems = [
                'index' => [
                        'controller' => 'SessionModule',
                        'action' => 'index',
                        'label' => $this->getLanguageService()->sL('LLL:EXT:sessions/Resources/Private/Language/locallang.xml:module.menu.item.calendar')
                ],
                'manage' => [
                        'controller' => 'SessionModule',
                        'action' => 'manage',
                        'parameters' => [
                                'type' => 'proposed'
                        ],
                        'label' => $this->getLanguageService()->sL('LLL:EXT:sessions/Resources/Private/Language/locallang.xml:module.menu.item.manage')
                ],
                'generateFirstSchedule' => [
                        'controller' => 'SessionModule',
                        'action' => 'generateFirstSchedule',
                        'label' => $this->getLanguageService()->sL('LLL:EXT:sessions/Resources/Private/Language/locallang.xml:module.menu.item.generateFirstSchedule')
                ],
        ];

        $menu = $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('BackendUserModuleMenu');

        foreach ($menuItems as  $menuItemConfig) {
            if ($this->request->getControllerName() === $menuItemConfig['controller']) {
                $isActive = $this->request->getControllerActionName() === $menuItemConfig['action'] ? true : false;
            } else {
                $isActive = false;
            }
            if(!isset($menuItemConfig['parameters'])) {
                $menuItemConfig['parameters'] = [];
            }
            $menuItem = $menu->makeMenuItem()
                ->setTitle($menuItemConfig['label'])
                ->setHref($this->getHref($menuItemConfig['controller'], $menuItemConfig['action'], $menuItemConfig['parameters']))
                ->setActive($isActive);
            $menu->addMenuItem($menuItem);
        }

        $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    /**
     * Generates module buttons.
     *
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     */
    protected function generateModuleButtons()
    {
        $buttonBar = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();
        $moduleName = $this->request->getPluginName();
        $getVars = $this->request->hasArgument('getVars') ? $this->request->getArgument('getVars') : [];
        $setVars = $this->request->hasArgument('setVars') ? $this->request->getArgument('setVars') : [];
        if (count($getVars) === 0) {
            $modulePrefix = strtolower('tx_' . $this->request->getControllerExtensionName() . '_' . $moduleName);
            $getVars = array('id', 'M', $modulePrefix);
        }
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setModuleName($moduleName)
            ->setGetVariables($getVars)
            ->setDisplayName('Sessions')
            ->setSetVariables($setVars);
        $buttonBar->addButton($shortcutButton);
    }

    public function indexAction()
    {

    }

    public function initializeIndexAction()
    {
        $this->checkAndTransformTypoScriptConfiguration();
    }

    protected function checkAndTransformTypoScriptConfiguration()
    {
        if( empty($this->settings['dd']['start']) || ($start = date_create($this->settings['dd']['start'])) === false ) {
            throw new \TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException('Please check your TypoScript Configuration and set \'settings.dd.start\' to a valid date');
        }
        $this->settings['dd']['start'] = $start;
        if( empty($this->settings['dd']['end']) || ($end = date_create($this->settings['dd']['end'])) === false ) {
            throw new \TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException('Please check your TypoScript Configuration and set \'settings.dd.start\' to a valid date');
        }
        $this->settings['dd']['end'] = $end;
    }

    /**
     * @param string $type = 'proposed'
     * @throws \InvalidArgumentException
     */
    public function manageAction($type = 'proposed')
    {
        if(!in_array($type, array_keys(ApiModuleController::$slugClassMap))) {
            throw new \InvalidArgumentException('type parameter must be one of the following: '.implode(array_keys(ApiModuleController::$slugClassMap)));
        }
        $this->view->assign('manageConfig', json_encode([
            'updateUrl' => $this->getHref('ApiModule', 'toggle', [
                'id' => '###id###',
                'type' => '###type###'
            ])
        ]));
        $this->view->assign('type', $type);
        $this->view->assign('sessions', $this->getFlatSessionObjects($type));
    }

    /**
     * Fetches a simple array of sessions (with vote count not being transformed into an objectstorage)
     * for a simple list view.
     */
    protected function getFlatSessionObjects($type)
    {
        $sessions = [];
        /** @var \TYPO3\CMS\Core\Database\DatabaseConnection $db */
        $db = $GLOBALS['TYPO3_DB'];
        $stmt = $db->prepare_SELECTquery('uid AS __identity, title, description, votes',
            'tx_sessions_domain_model_session',
            ' type = :type AND deleted = 0 '.\TYPO3\CMS\Backend\Utility\BackendUtility::BEenableFields('tx_sessions_domain_model_session'),
            '', ' votes DESC ', '', [':type' => ApiModuleController::$slugClassMap[$type]]);
        if($stmt->execute()) {
            while($row = $stmt->fetch(\TYPO3\CMS\Core\Database\PreparedStatement::FETCH_ASSOC)) {
                $sessions[] = $row;
            }
            $stmt->free();
        }
        return $sessions;
    }

    public function generateFirstScheduleAction()
    {

    }

    /**
     * @param array $config
     * @param boolean $considerTopics
     * @param integer $iterations
     */
    public function createTimeTableAction($config, $considerTopics, $iterations)
    {
	    // Get all sessions
	    $sessions = $this->sessionRepository->findAll()->toArray();
	    // Get all rooms
	    $rooms = $this->roomRepository->findAllLimited(6)->toArray();
	    // Generate timetable with service
	    $success = $this->createTimetableService->generateTimetable($config, $sessions, $rooms, $iterations, $considerTopics);
	    $incompleteSessions = array();
	    if(!$success)
	    {
		    $incompleteSessions = $this->createTimetableService->getUnassignedSessions();
	    }

	    // Save changes on sessions
	    foreach($this->createTimetableService->getAssignedSessions() as $assignedSession)
	    {
		    $this->sessionRepository->update($assignedSession);
	    }

	    $this->redirect('index', '', '', array('incompleteSessions' => $incompleteSessions, 'creationDone' => true));
    }

    /*public function errorAction(){
        var_dump($this->request->getOriginalRequestMappingResults()->forProperty('session')->getFlattenedErrors());
    }*/

    public function testAction()
    {

    }

    /**
     * Creates te URI for a backend action
     *
     * @param string $controller
     * @param string $action
     * @param array $parameters
     * @return string
     */
    protected function getHref($controller, $action, $parameters = [])
    {
        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($this->request);
        return $uriBuilder->reset()->uriFor($action, $parameters, $controller);
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @param \TYPO3\Sessions\Planning\Utility $utility
     */
    public function injectUtility(\TYPO3\Sessions\Planning\Utility $utility)
    {
        $this->utility = $utility;
    }

}
