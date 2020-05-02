<?php

namespace Cissee\Webtrees\Module\ClippingsCart;

use Fisharebest\Webtrees\I18N;
use Vesta\ControlPanel\Model\ControlPanelCheckbox;
use Vesta\ControlPanel\Model\ControlPanelPreferences;
use Vesta\ControlPanel\Model\ControlPanelSection;
use Vesta\ControlPanel\Model\ControlPanelSubsection;

trait ClippingsCartModuleTrait {

  protected function getMainTitle() {
    return I18N::translate('Vesta Clippings Cart');
  }

  public function getShortDescription() {
    return I18N::translate('Select records from your family tree and save them as a GEDCOM file. Replacement for the original \'Clippings Cart\' module.');
  }

  protected function getFullDescription() {
    $description = array();
    $description[] = 
            /* I18N: Module Configuration */I18N::translate('Select records from your family tree and save them as a GEDCOM file.');
    return $description;
  }

  protected function createPrefs() {
    $generalSub = array();
    $generalSub[] = new ControlPanelSubsection(
            /* I18N: Configuration option */I18N::translate('Displayed title'),
            array(
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include the %1$s symbol in the module title', $this->getVestaSymbol()),
                null,
                'VESTA',
                '1'),
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
