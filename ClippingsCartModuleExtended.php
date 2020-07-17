<?php

namespace Cissee\Webtrees\Module\ClippingsCart;

use Aura\Router\Route;
use Cissee\WebtreesExt\Module\ClippingsCartModule;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Vesta\Hook\HookInterfaces\FunctionsClippingsCartUtils;
use Vesta\VestaModuleTrait;

class ClippingsCartModuleExtended extends ClippingsCartModule implements 
  ModuleCustomInterface, 
  ModuleConfigInterface, 
  ModuleMenuInterface {

  use ModuleCustomTrait, ModuleConfigTrait, VestaModuleTrait {
    VestaModuleTrait::customTranslations insteadof ModuleCustomTrait;
    VestaModuleTrait::customModuleLatestVersion insteadof ModuleCustomTrait;
    VestaModuleTrait::getAssetAction insteadof ModuleCustomTrait;
    VestaModuleTrait::assetUrl insteadof ModuleCustomTrait;
    
    VestaModuleTrait::getConfigLink insteadof ModuleConfigTrait;
  }
  
  use ClippingsCartModuleTrait;
  
  public function __construct(UserService $user_service) {
    parent::__construct($user_service);
  }

  public function customModuleAuthorName(): string {
    return 'Richard Cissée';
  }

  public function customModuleVersion(): string {
    return file_get_contents(__DIR__ . '/latest-version.txt');
  }

  public function customModuleLatestVersionUrl(): string {
    return 'https://raw.githubusercontent.com/vesta-webtrees-2-custom-modules/vesta_clippings_cart/master/latest-version.txt';
  }

  public function customModuleSupportUrl(): string {
    return 'https://cissee.de';
  }
    
  public function resourcesFolder(): string {
    return __DIR__ . '/resources/';
  }
  
  /**
   * Bootstrap the module
   */
  public function onBoot(): void {
    $this->flashWhatsNew('\Cissee\Webtrees\Module\ClippingsCart\WhatsNew', 1);
  }
  
  protected function menuTitle(): string {
    return $this->getMenuTitle(MoreI18N::xlate("Clippings cart"));
  }
  
  protected function getAddToClippingsCartRoute(Route $route, Tree $tree): ?string {
    $ret = parent::getAddToClippingsCartRoute($route, $tree);
    if ($ret != null) {
      return $ret;
    }
    
    return FunctionsClippingsCartUtils::getAddToClippingsCartRoute($this, $route, $tree);
  }
  
  protected function getDirectLinkTypes(Tree $tree): Collection {
    $types = new Collection(["OBJE", "NOTE","SOUR","REPO"]);
    return $types->merge(FunctionsClippingsCartUtils::getDirectLinkTypes($this, $tree));
  }
  
  protected function getIndirectLinks(GedcomRecord $record): Collection {
    return FunctionsClippingsCartUtils::getIndirectLinks($this, $record);
  }
  
  //see also webtrees issue #3181
  protected function getTransitiveLinks(GedcomRecord $record): Collection {
    return FunctionsClippingsCartUtils::getTransitiveLinks($this, $record);
  }
  
  //note that we don't bother adjusting
  //$cart = Session::get('cart', []);
  //
  //i.e. cart filled via this module is the same as cart filled via original module

  public function addRecordToCart(GedcomRecord $record): void
  {
      $cart = Session::get('cart', []);

      $tree_name = $record->tree()->name();

      // Add this record
      $cart[$tree_name][$record->xref()] = true;

      //[RC] adjusted
      // Add directly linked types.
      preg_match_all('/\n\d (?:' . $this->getDirectLinkTypes($record->tree())->implode("|") . ') @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);
      
      foreach ($matches[1] as $match) {
          $cart[$tree_name][$match] = true;
          
          $linkedRecord = Factory::gedcomRecord()->make($match, $record->tree());
          foreach ($this->getTransitiveLinks($linkedRecord) as $match2) {
            $cart[$tree_name][$match2] = true;
          }
      }

      foreach ($this->getIndirectLinks($record) as $match) {
          $cart[$tree_name][$match] = true;
          
          $linkedRecord = Factory::gedcomRecord()->make($match, $record->tree());
          foreach ($this->getTransitiveLinks($linkedRecord) as $match2) {            
            $cart[$tree_name][$match2] = true;
          }
      }
      
      Session::put('cart', $cart);
  }
}
