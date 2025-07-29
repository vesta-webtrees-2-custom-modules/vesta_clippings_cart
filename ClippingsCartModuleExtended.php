<?php

namespace Cissee\Webtrees\Module\ClippingsCart;

use Cissee\WebtreesExt\Module\ModuleMetaInterface;
use Cissee\WebtreesExt\Module\ModuleMetaTrait;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\ClippingsCartModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Vesta\Hook\HookInterfaces\ClippingsCartAddToCartInterface;
use Vesta\Hook\HookInterfaces\FunctionsClippingsCartUtils;
use Vesta\VestaModuleTrait;
use function redirect;

class ClippingsCartModuleExtended extends ClippingsCartModule implements
    ModuleCustomInterface,
    ModuleMetaInterface,
    ModuleConfigInterface,
    ModuleMenuInterface,
    ClippingsCartAddToCartInterface {

    use ModuleCustomTrait,
        ModuleMetaTrait,
        ModuleConfigTrait,
        VestaModuleTrait {
        VestaModuleTrait::title as trait_title;
        VestaModuleTrait::customTranslations insteadof ModuleCustomTrait;
        VestaModuleTrait::getAssetAction insteadof ModuleCustomTrait;
        VestaModuleTrait::assetUrl insteadof ModuleCustomTrait;
        VestaModuleTrait::getConfigLink insteadof ModuleConfigTrait;
        ModuleMetaTrait::customModuleVersion insteadof ModuleCustomTrait;
        ModuleMetaTrait::customModuleLatestVersion insteadof ModuleCustomTrait;
    }

    use ClippingsCartModuleTrait;

    public function __construct(
        GedcomExportService $gedcom_export_service,
        LinkedRecordService $linked_record_service,
        PhpService $php_service) {

        parent::__construct(
            $gedcom_export_service,
            $linked_record_service,
            $php_service);
    }

    public function customModuleAuthorName(): string {
        return 'Richard Cissée';
    }

    public function customModuleMetaDatasJson(): string {
        return file_get_contents(__DIR__ . '/metadata.json');
    }

    public function customModuleLatestMetaDatasJsonUrl(): string {
        return 'https://raw.githubusercontent.com/vesta-webtrees-2-custom-modules/vesta_clippings_cart/master/metadata.json';
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

    /** @var bool */
    protected $switchTitle = false;

    public function title(): string {
        //usually trait_title(), but for the method called via parent::getMenu we need menuTitle()!
        if ($this->switchTitle) {
            return $this->menuTitle();
        }
        return $this->trait_title();
    }

    public function getMenu(Tree $tree): ?Menu {
        $this->switchTitle = true;
        $ret = parent::getMenu($tree);
        $this->switchTitle = false;
        return $ret;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddLocationAction(ServerRequestInterface $request): ResponseInterface {

        $tree = Validator::attributes($request)->tree();

        $xref = $request->getQueryParams()['xref'] ?? '';

        $location = Registry::locationFactory()->make($xref, $tree);
        $location = Auth::checkLocationAccess($location);
        $name = $location->fullName();

        $options = [
            'record' => $name,
        ];

        $additionalOptions = $this->getAddLocationActionAdditionalOptions($location);
        $options = array_merge($options, $additionalOptions);

        $title = MoreI18N::xlate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
                'options' => $options,
                'record' => $location,
                'title' => $title,
                'tree' => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddLocationAction(ServerRequestInterface $request): ResponseInterface {
        $tree = Validator::attributes($request)->tree();

        $params = (array) $request->getParsedBody();

        $xref = $params['xref'] ?? '';
        $option = $params['option'] ?? '';

        $location = Registry::locationFactory()->make($xref, $tree);
        $location = Auth::checkLocationAccess($location);

        $this->addLocationToCart($location);

        $this->postAddLocationActionHandleOption($location, $option);

        return redirect($location->url());
    }

    /**
     * @param GedcomRecord $record
     */
    protected function addLocationLinksToCart(GedcomRecord $record): void {
        preg_match_all('/\n\d _LOC @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $location = Registry::locationFactory()->make($xref, $record->tree());

            if ($location instanceof Location && $location->canShow()) {
                $this->addLocationToCart($location);
            }
        }

        //added
        foreach ($this->getIndirectLocations($record) as $xref) {
            $location = Registry::locationFactory()->make($xref, $record->tree());

            if ($location instanceof Location && $location->canShow()) {
                $this->addLocationToCart($location);
            }
        }
    }

    protected function getAddLocationActionAdditionalOptions(Location $location): array {
        return FunctionsClippingsCartUtils::getAddLocationActionAdditionalOptions($location);
    }

    //e.g. _LOC with linked INDI/FAM (not possible in standard clippings cart)
    protected function postAddLocationActionHandleOption(Location $location, string $option) {
        return FunctionsClippingsCartUtils::postAddLocationActionHandleOption($this, $location, $option);
    }

    //e.g. INDI/FAM to _LOC, indirectly (not possible in standard clippings cart)
    protected function getIndirectLocations(GedcomRecord $record): Collection {
        return FunctionsClippingsCartUtils::getIndirectLocations($record);
    }

    public function doAddIndividualToCart(Individual $individual): void {
        $this->addIndividualToCart($individual);
    }

    public function doAddFamilyToCart(Family $family): void {
        $this->addFamilyToCart($family);
    }

    public function doAddFamilyAndChildrenToCart(Family $family): void {
        $this->addFamilyAndChildrenToCart($family);
    }

    ////////////////////////////////////////////////////////////////////////////
    //extension for 'full ancestor branch'

    // What to add to the cart?
    private const ADD_RECORD_ONLY        = 'record';
    private const ADD_CHILDREN           = 'children';
    private const ADD_DESCENDANTS        = 'descendants';
    private const ADD_PARENT_FAMILIES    = 'parents';
    private const ADD_SPOUSE_FAMILIES    = 'spouses';
    private const ADD_ANCESTORS          = 'ancestors';
    private const ADD_ANCESTOR_FAMILIES  = 'families';
    private const ADD_LINKED_INDIVIDUALS = 'linked';

    private const ADD_FULL_ANCESTOR_BRANCH = 'fullAncestorBranch';

    public function getAddIndividualAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree       = Validator::attributes($request)->tree();
        $xref       = Validator::queryParams($request)->isXref()->string('xref');
        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual);
        $name       = $individual->fullName();

        if ($individual->sex() === 'F') {
            $options = [
                self::ADD_RECORD_ONLY       => $name,
                self::ADD_PARENT_FAMILIES   => MoreI18N::xlate('%s, her parents and siblings', $name),
                self::ADD_SPOUSE_FAMILIES   => MoreI18N::xlate('%s, her spouses and children', $name),
                self::ADD_ANCESTORS         => MoreI18N::xlate('%s and her ancestors', $name),
                self::ADD_ANCESTOR_FAMILIES => MoreI18N::xlate('%s, her ancestors and their families', $name),
                self::ADD_DESCENDANTS       => MoreI18N::xlate('%s, her spouses and descendants', $name),

                self::ADD_FULL_ANCESTOR_BRANCH       => I18N::translate('%s and her full ancestor branch', $name),
            ];
        } else {
            $options = [
                self::ADD_RECORD_ONLY       => $name,
                self::ADD_PARENT_FAMILIES   => MoreI18N::xlate('%s, his parents and siblings', $name),
                self::ADD_SPOUSE_FAMILIES   => MoreI18N::xlate('%s, his spouses and children', $name),
                self::ADD_ANCESTORS         => MoreI18N::xlate('%s and his ancestors', $name),
                self::ADD_ANCESTOR_FAMILIES => MoreI18N::xlate('%s, his ancestors and their families', $name),
                self::ADD_DESCENDANTS       => MoreI18N::xlate('%s, his spouses and descendants', $name),

                self::ADD_FULL_ANCESTOR_BRANCH       => I18N::translate('%s and his full ancestor branch', $name),
            ];
        }

        $title = MoreI18N::xlate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
            'options' => $options,
            'record'  => $individual,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    public function postAddIndividualAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree   = Validator::attributes($request)->tree();
        $xref   = Validator::parsedBody($request)->isXref()->string('xref');
        $option = Validator::parsedBody($request)->string('option');

        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual);

        switch ($option) {
            case self::ADD_RECORD_ONLY:
                $this->addIndividualToCart($individual);
                break;

            case self::ADD_PARENT_FAMILIES:
                foreach ($individual->childFamilies() as $family) {
                    $this->addFamilyAndChildrenToCart($family);
                }
                break;

            case self::ADD_SPOUSE_FAMILIES:
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndChildrenToCart($family);
                }
                break;

            case self::ADD_ANCESTORS:
                $this->addAncestorsToCart($individual);
                break;

            case self::ADD_ANCESTOR_FAMILIES:
                $this->addAncestorFamiliesToCart($individual);
                break;

            case self::ADD_DESCENDANTS:
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndDescendantsToCart($family);
                }
                break;

             case self::ADD_FULL_ANCESTOR_BRANCH:
                $this->addFullAncestorBranchToCart($individual);
                break;
        }

        return redirect($individual->url());
    }

    ////////////////////////////////////////////////////////////////////////////

    protected function addFullAncestorBranchToCart(Individual $individual): void
    {
        //TODO add ASSO?

        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];

        //first add blockers

        foreach ($individual->spouseFamilies() as $family) {
            $cart[$family->tree()->name()][$family->xref()] = true;
        }

        $this->addIndividualToCartAndRecurse($individual, $cart);

        //finally remove blockers (assuming they weren't reached via other paths)

        foreach ($individual->spouseFamilies() as $family) {
            unset($cart[$family->tree()->name()][$family->xref()]);
        }


        Session::put('cart', $cart);
    }

    protected function addIndividualToCartAndRecurse(Individual $individual, &$cart): void
    {
        $tree = $individual->tree()->name();
        $xref = $individual->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            $this->addLocationLinksToCart2($individual, $cart);
            $this->addMediaLinksToCart2($individual, $cart);
            $this->addNoteLinksToCart2($individual, $cart);
            $this->addSourceLinksToCart2($individual, $cart);

            //ancestors
            foreach ($individual->childFamilies() as $family) {
                $this->addFamilyToCartAndRecurse($family, $cart);
            }

            //descendants
            foreach ($individual->spouseFamilies() as $family) {
                $this->addFamilyToCartAndRecurse($family, $cart);
            }
        }
    }

    protected function addFamilyToCartAndRecurse(Family $family, &$cart): void
    {
        $tree = $family->tree()->name();
        $xref = $family->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            $this->addLocationLinksToCart2($family, $cart);
            $this->addMediaLinksToCart2($family, $cart);
            $this->addNoteLinksToCart2($family, $cart);
            $this->addSourceLinksToCart2($family, $cart);
            $this->addSubmitterLinksToCart2($family, $cart);

            foreach ($family->spouses() as $spouse) {
                $this->addIndividualToCartAndRecurse($spouse, $cart);
            }

            foreach ($family->children() as $child) {
                $this->addIndividualToCartAndRecurse($child, $cart);
            }
        }
    }

    protected function addLocationToCart2(Location $location, &$cart): void
    {
        $tree = $location->tree()->name();
        $xref = $location->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            $this->addLocationLinksToCart2($location, $cart);
            $this->addMediaLinksToCart2($location, $cart);
            $this->addNoteLinksToCart2($location, $cart);
            $this->addSourceLinksToCart2($location, $cart);
        }
    }

    protected function addLocationLinksToCart2(GedcomRecord $record, &$cart): void
    {
        preg_match_all('/\n\d _LOC @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $location = Registry::locationFactory()->make($xref, $record->tree());

            if ($location instanceof Location && $location->canShow()) {
                $this->addLocationToCart2($location, $cart);
            }
        }
    }

    protected function addMediaToCart2(Media $media, &$cart): void
    {
        $tree = $media->tree()->name();
        $xref = $media->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            $this->addNoteLinksToCart2($media, $cart);
        }
    }

    protected function addMediaLinksToCart2(GedcomRecord $record, &$cart): void
    {
        preg_match_all('/\n\d OBJE @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $media = Registry::mediaFactory()->make($xref, $record->tree());

            if ($media instanceof Media && $media->canShow()) {
                $this->addMediaToCart2($media, $cart);
            }
        }
    }

    protected function addNoteToCart2(Note $note, &$cart): void
    {
        $tree = $note->tree()->name();
        $xref = $note->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;
        }
    }

    protected function addNoteLinksToCart2(GedcomRecord $record, &$cart): void
    {
        preg_match_all('/\n\d NOTE @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $note = Registry::noteFactory()->make($xref, $record->tree());

            if ($note instanceof Note && $note->canShow()) {
                $this->addNoteToCart2($note, $cart);
            }
        }
    }

    protected function addSourceToCart2(Source $source, &$cart): void
    {
        $tree = $source->tree()->name();
        $xref = $source->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            $this->addNoteLinksToCart2($source, $cart);
            $this->addRepositoryLinksToCart2($source, $cart);
        }
    }

    protected function addSourceLinksToCart2(GedcomRecord $record, &$cart): void
    {
        preg_match_all('/\n\d SOUR @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $source = Registry::sourceFactory()->make($xref, $record->tree());

            if ($source instanceof Source && $source->canShow()) {
                $this->addSourceToCart2($source, $cart);
            }
        }
    }

    protected function addRepositoryToCart2(Repository $repository, &$cart): void
    {
        $tree = $repository->tree()->name();
        $xref = $repository->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            $this->addNoteLinksToCart2($repository, $cart);
        }
    }

    protected function addRepositoryLinksToCart2(GedcomRecord $record, &$cart): void
    {
        preg_match_all('/\n\d REPO @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $repository = Registry::repositoryFactory()->make($xref, $record->tree());

            if ($repository instanceof Repository && $repository->canShow()) {
                $this->addRepositoryToCart2($repository, $cart);
            }
        }
    }

    protected function addSubmitterToCart2(Submitter $submitter, &$cart): void
    {
        $tree = $submitter->tree()->name();
        $xref = $submitter->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            $this->addNoteLinksToCart2($submitter, $cart);
        }
    }

    protected function addSubmitterLinksToCart2(GedcomRecord $record, &$cart): void
    {
        preg_match_all('/\n\d SUBM @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $submitter = Registry::submitterFactory()->make($xref, $record->tree());

            if ($submitter instanceof Submitter && $submitter->canShow()) {
                $this->addSubmitterToCart2($submitter, $cart);
            }
        }
    }
}
