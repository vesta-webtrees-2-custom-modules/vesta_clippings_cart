<?php

namespace Cissee\Webtrees\Module\ClippingsCart;

use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\I18N;
use Vesta\ControlPanelUtils\Model\ControlPanelCheckbox;
use Vesta\ControlPanelUtils\Model\ControlPanelPreferences;
use Vesta\ControlPanelUtils\Model\ControlPanelSection;
use Vesta\ControlPanelUtils\Model\ControlPanelSubsection;

trait ClippingsCartModuleTrait {

  protected function getMainTitle() {
    return I18N::translate('Vesta Clippings Cart');
  }

  public function getShortDescription() {
    return MoreI18N::xlate('Select records from your family tree and save them as a GEDCOM file.') . " " . I18N::translate('Replacement for the original \'Clippings Cart\' module.');
  }

  protected function getFullDescription() {
    $description = array();
    $description[] = $this->getShortDescription();
    return $description;
  }

  protected function createPrefs() {
    $generalSub = array();
    $generalSub[] = new ControlPanelSubsection(
            /* I18N: Configuration option */I18N::translate('Displayed title'),
            array(
        /*new ControlPanelCheckbox(
                I18N::translate('Include the %1$s symbol in the module title', $this->getVestaSymbol()),
                null,
                'VESTA',
                '1'),*/
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include the %1$s symbol in the menu title', $this->getVestaSymbol()),
                /* I18N: Module Configuration */I18N::translate('Deselect in order to have the menu appear exactly as the original menu.'),
                'VESTA_MENU',
                '1')));

    $sections = array();
    $sections[] = new ControlPanelSection(
            /* I18N: Configuration option */I18N::translate('General'),
            null,
            $generalSub);

    return new ControlPanelPreferences($sections);
  }

}
