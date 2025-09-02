<?php

namespace LLMSGAA;

// Exit if accessed directly to protect from direct URL access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central class to register and initialize plugin components.
 *
 * This class is called from the main plugin file and serves
 * as the entry point for hooking into WordPress.
 */

class PluginRegistrar {

    /**
     * Static init method to attach all necessary hooks and load plugin features.
     */
public static function init() {

    Core::init_hooks();
    \LLMSGAA\MetaBoxes::init_hooks();
    \LLMSGAA\Feature\GroupAdmin\Controller::init_hooks();
    \LLMSGAA\Feature\Settings\SettingsPage::init_hooks();
    \LLMSGAA\AdminColumns::init_hooks();
    \LLMSGAA\Common\Assets::init_hooks();
    \LLMSGAA\Feature\GroupAdmin\FormHandler::init();
    \LLMSGAA\Feature\AccessLogic\Override::init();
    \LLMSGAA\Feature\Shortcodes\Controller::init();
    \LLMSGAA\Feature\GroupAdmin\MyGroupsController::init();
    \LLMSGAA\Feature\FormHandler\Controller::init_hooks();
    \LLMSGAA\Feature\Reports\Controller::init_hooks();
    \LLMSGAA\Feature\GroupAdmin\Reporting::init_hooks();
    \LLMSGAA\Feature\Invitation\SeatSaveHandler::init_hooks();
    \LLMSGAA\Feature\Scheduler\ScheduleHandler::init();
    \LLMSGAA\Feature\BulkAssign\BulkAssignHandler::init();
    
    }
}
