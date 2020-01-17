<?php
/* Copyright (C) 2011	   Dimitri Mouillard	<dmouillard@teclib.com>
 * Copyright (C) 2013-2018 Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2012-2016 Regis Houssin	<regis.houssin@inodbox.com>
 * Copyright (C) 2018      Charlene Benke	<charlie@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *   	\file       htdocs/holiday/list.php
 *		\ingroup    holiday
 *		\brief      List of holiday
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
require_once DOL_DOCUMENT_ROOT.'/holiday/common.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';

// Load translation files required by the page
$langs->loadLangs(array('users', 'holidays', 'hrm'));

// Protection if external user
if ($user->societe_id > 0) accessforbidden();

$action     = GETPOST('action', 'alpha');												// The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction = GETPOST('massaction', 'alpha');											// The bulk action (combo box choice into lists)
$show_files = GETPOST('show_files', 'int');												// Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm', 'alpha');												// Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha');												// We click on a Cancel button
$toselect   = GETPOST('toselect', 'array');												// Array of ids of elements selected into a list
$contextpage= GETPOST('contextpage', 'aZ')?GETPOST('contextpage', 'aZ'):'myobjectlist';   // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');											// Go back to a dedicated page
$optioncss  = GETPOST('optioncss', 'aZ');												// Option for the css output (always '' except when 'print')

$childids = $user->getAllChildIds(1);

// Security check
$socid=0;
if ($user->societe_id > 0)	// Protection if external user
{
	//$socid = $user->societe_id;
	accessforbidden();
}
$result = restrictedArea($user, 'holiday', $id, '');
$id = GETPOST('id', 'int');
// If we are on the view of a specific user
if ($id > 0)
{
    $canread=0;
    if ($id == $user->id) $canread=1;
    if (! empty($user->rights->holiday->read_all)) $canread=1;
    if (! empty($user->rights->holiday->read) && in_array($id, $childids)) $canread=1;
    if (! $canread)
    {
        accessforbidden();
    }
}

// Load variable for pagination
$limit = GETPOST('limit', 'int')?GETPOST('limit', 'int'):$conf->liste_limit;
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOST('page', 'int');
if (empty($page) || $page == -1) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize technical objects
$object=new Holiday($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction=$conf->holiday->dir_output . '/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('holidaylist'));     // Note that conf->hooks_modules contains array
// Fetch optionals attributes and labels
$extralabels = $extrafields->fetch_name_optionals_label('holiday');
$search_array_options=$extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Default sort order (if not yet defined by previous GETPOST)
if (! $sortfield) $sortfield="cp.rowid";
if (! $sortorder) $sortorder="DESC";


$sall                = trim((GETPOST('search_all', 'alphanohtml')!='')?GETPOST('search_all', 'alphanohtml'):GETPOST('sall', 'alphanohtml'));
$search_ref          = GETPOST('search_ref', 'alphanohtml');
$search_day_create   = GETPOST('search_day_create', 'int');
$search_month_create = GETPOST('search_month_create', 'int');
$search_year_create  = GETPOST('search_year_create', 'int');
$search_day_start    = GETPOST('search_day_start', 'int');
$search_month_start  = GETPOST('search_month_start', 'int');
$search_year_start   = GETPOST('search_year_start', 'int');
$search_day_end      = GETPOST('search_day_end', 'int');
$search_month_end    = GETPOST('search_month_end', 'int');
$search_year_end     = GETPOST('search_year_end', 'int');
$search_employee     = GETPOST('search_employee', 'int');
$search_valideur     = GETPOST('search_valideur', 'int');
$search_statut       = GETPOST('search_statut', 'int');
$search_type         = GETPOST('search_type', 'int');

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
    'cp.description'=>'Description',
    'uu.lastname'=>'EmployeeLastname',
    'uu.firstname'=>'EmployeeFirstname'
);



/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) { $action='list'; $massaction=''; }
if (! GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') { $massaction=''; }

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') ||GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
	{
		$search_ref="";
		$search_month_create="";
		$search_year_create="";
	    $search_month_start="";
		$search_year_start="";
		$search_month_end="";
		$search_year_end="";
		$search_employee="";
		$search_valideur="";
		$search_statut="";
		$search_type='';
		$toselect='';
		$search_array_options=array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha'))
	{
		$massaction='';     // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass='Holiday';
	$objectlabel='Holiday';
	$permtoread = $user->rights->holiday->read;
	$permtodelete = $user->rights->holiday->delete;
	$uploaddir = $conf->holiday->dir_output;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}




/*
 * View
 */

$form = new Form($db);
$formother = new FormOther($db);

$holiday = new Holiday($db);
$holidaystatic=new Holiday($db);
$fuser = new User($db);

// Update sold
$result = $holiday->updateBalance();

$max_year = 5;
$min_year = 10;
$filter='';

llxHeader('', $langs->trans('CPTitreMenu'));

$order = $db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);

// Ref
if(!empty($search_ref))
{
    $filter.= " AND cp.rowid = ".(int) $db->escape($search_ref);
}
// Start date
$filter.= dolSqlDateFilter("cp.date_debut", $search_day_start, $search_month_start, $search_year_start);
// End date
$filter.= dolSqlDateFilter("cp.date_fin", $search_day_end, $search_month_end, $search_year_end);
// Create date
$filter.= dolSqlDateFilter("cp.date_create", $search_day_create, $search_month_create, $search_year_create);
// Employee
if(!empty($search_employee) && $search_employee != -1) {
    $filter.= " AND cp.fk_user = '".$db->escape($search_employee)."'\n";
}
// Validator
if(!empty($search_valideur) && $search_valideur != -1) {
    $filter.= " AND cp.fk_validator = '".$db->escape($search_valideur)."'\n";
}
// Type
if (!empty($search_type) && $search_type != -1) {
	$filter.= ' AND cp.fk_type IN ('.$db->escape($search_type).')';
}
// Status
if(!empty($search_statut) && $search_statut != -1) {
    $filter.= " AND cp.statut = '".$db->escape($search_statut)."'\n";
}
// Search all
if (!empty($sall))
{
	$filter.= natural_search(array_keys($fieldstosearchall), $sall);
}

if (empty($user->rights->holiday->read_all)) $filter.=' AND cp.fk_user IN ('.join(',', $childids).')';


// Récupération de l'ID de l'utilisateur
$user_id = $user->id;

if ($id > 0)
{
	// Charge utilisateur edite
	$fuser->fetch($id, '', '', 1);
	$fuser->getrights();
	$user_id = $fuser->id;

	$search_employee = $user_id;
}

// Récupération des congés payés de l'utilisateur ou de tous les users de sa hierarchy
// Load array $holiday->holiday
if (empty($user->rights->holiday->read_all) || $id > 0)
{
	if ($id > 0) $result = $holiday->fetchByUser($id, $order, $filter);
	else  $result = $holiday->fetchByUser(join(',', $childids), $order, $filter);
}
else
{
    $result = $holiday->fetchAll($order, $filter);
}
// Si erreur SQL
if ($result == '-1')
{
    print load_fiche_titre($langs->trans('CPTitreMenu'), '', 'title_hrm.png');

    dol_print_error($db, $langs->trans('Error').' '.$holiday->error);
    exit();
}


// Show table of vacations

$num = count($holiday->holiday);

$arrayofselected=is_array($toselect)?$toselect:array();

$param='';
if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.urlencode($contextpage);
if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.urlencode($limit);
if ($optioncss != '')     $param.='&optioncss='.urlencode($optioncss);
if ($search_ref)          $param.='&search_ref='.urlencode($search_ref);
if ($search_day_create)          $param.='&search_day_create='.urlencode($search_day_create);
if ($search_month_create)        $param.='&search_month_create='.urlencode($search_month_create);
if ($search_year_create)         $param.='&search_year_create='.urlencode($search_year_create);
if ($search_search_day_start)    $param.='&search_day_start='.urlencode($search_day_start);
if ($search_month_start)         $param.='&search_month_start='.urlencode($search_month_start);
if ($search_year_start)          $param.='&search_year_start='.urlencode($search_year_start);
if ($search_day_end)             $param.='&search_day_end='.urlencode($search_day_end);
if ($search_month_end)           $param.='&search_month_end='.urlencode($search_month_end);
if ($search_year_end)            $param.='&search_year_end='.urlencode($search_year_end);
if ($search_employee > 0) $param.='&search_employee='.urlencode($search_employee);
if ($search_valideur > 0) $param.='&search_valideur='.urlencode($search_valideur);
if ($search_type > 0)     $param.='&search_type='.urlencode($search_type);
if ($search_statut > 0)   $param.='&search_statut='.urlencode($search_statut);

// List of mass actions available
$arrayofmassactions =  array(
//'presend'=>$langs->trans("SendByMail"),
//'builddoc'=>$langs->trans("PDFMerge"),
);
if ($user->rights->holiday->delete) $arrayofmassactions['predelete']='<span class="fa fa-trash paddingrightonly"></span>'.$langs->trans("Delete");
if (in_array($massaction, array('presend','predelete'))) $arrayofmassactions=array();
$massactionbutton=$form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
if ($id > 0) print '<input type="hidden" name="id" value="'.$id.'">';

if ($id > 0)		// For user tab
{
	$title = $langs->trans("User");
	$linkback = '<a href="'.DOL_URL_ROOT.'/user/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
	$head = user_prepare_head($fuser);

	dol_fiche_head($head, 'paidholidays', $title, -1, 'user');

    dol_banner_tab($fuser, 'id', $linkback, $user->rights->user->user->lire || $user->admin);

	if (empty($conf->global->HOLIDAY_HIDE_BALANCE))
	{
	    print '<div class="underbanner clearboth"></div>';

	    print '<br>';

	    showMyBalance($holiday, $user_id);
	}

	dol_fiche_end();

	// Buttons for actions

	print '<div class="tabsAction">';

	$canedit=(($user->id == $user_id && $user->rights->holiday->write) || ($user->id != $user_id && $user->rights->holiday->write_all));

	if ($canedit)
	{
		print '<a href="'.DOL_URL_ROOT.'/holiday/card.php?action=request&fuserid='.$user_id.'" class="butAction">'.$langs->trans("AddCP").'</a>';
	}

	print '</div>';
}
else
{
	$nbtotalofrecords = count($holiday->holiday);
   	//print $num;
    //print count($holiday->holiday);

	$newcardbutton='';
	if ($user->rights->holiday->write)
	{
		$newcardbutton.= dolGetButtonTitle($langs->trans('MenuAddCP'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/holiday/card.php?action=request');
    }

	print_barre_liste($langs->trans("ListeCP"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'title_hrm.png', 0, $newcardbutton, '', $limit);

	$topicmail="Information";
	$modelmail="leaverequest";
	$objecttmp=new Holiday($db);
	$trackid='leav'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';
}

if ($sall)
{
    foreach($fieldstosearchall as $key => $val) $fieldstosearchall[$key]=$langs->trans($val);
    print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $sall) . join(', ', $fieldstosearchall).'</div>';
}

$varpage=empty($contextpage)?$_SERVER["PHP_SELF"]:$contextpage;
$selectedfields='';	// $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage);	// This also change content of $arrayfields
$selectedfields.=$form->showCheckAddButtons('checkforselect', 1);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";

// Filters
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre">';
print '<input class="flat" size="4" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
print '</td>';

// Create date
print '<td class="liste_titre center">';
print '<input class="flat valignmiddle" type="text" size="1" maxlength="2" name="search_month_create" value="'.dol_escape_htmltag($search_month_create).'">';
$formother->select_year($search_year_create, 'search_year_create', 1, $min_year, 0);
print '</td>';


$morefilter = 'AND employee = 1';
if (! empty($conf->global->HOLIDAY_FOR_NON_SALARIES_TOO)) $morefilter = '';

// User
$disabled=0;
// If into the tab holiday of a user ($id is set in such a case)
if ($id && ! GETPOSTISSET('search_employee'))
{
	$search_employee=$id;
	$disabled=1;
}
if (! empty($user->rights->holiday->read_all))	// Can see all
{
	if (GETPOSTISSET('search_employee')) $search_employee=GETPOST('search_employee', 'int');
	print '<td class="liste_titre maxwidthonsmartphone left">';
	print $form->select_dolusers($search_employee, "search_employee", 1, "", $disabled, '', '', 0, 0, 0, $morefilter, 0, '', 'maxwidth200');
    print '</td>';
}
else
{
	if (GETPOSTISSET('search_employee')) $search_employee=GETPOST('search_employee', 'int');
    print '<td class="liste_titre maxwidthonsmartphone left">';
    print $form->select_dolusers($search_employee, "search_employee", 1, "", $disabled, 'hierarchyme', '', 0, 0, 0, $morefilter, 0, '', 'maxwidth200');
    print '</td>';
}

// Approve
if ($user->rights->holiday->read_all)
{
    print '<td class="liste_titre maxwidthonsmartphone left">';

    $validator = new UserGroup($db);
    $excludefilter=$user->admin?'':'u.rowid <> '.$user->id;
    $valideurobjects = $validator->listUsersForGroup($excludefilter);
    $valideurarray = array();
    foreach($valideurobjects as $val) $valideurarray[$val->id]=$val->id;
    print $form->select_dolusers($search_valideur, "search_valideur", 1, "", 0, $valideurarray, '', 0, 0, 0, $morefilter, 0, '', 'maxwidth200');
    print '</td>';
}
else
{
    print '<td class="liste_titre">&nbsp;</td>';
}

// Type
print '<td class="liste_titre">';
if (empty($mysoc->country_id)) {
	setEventMessages(null, array($langs->trans("ErrorSetACountryFirst"),$langs->trans("CompanyFoundation")), 'errors');
} else {
	$typeleaves=$holidaystatic->getTypes(1, -1);
	$arraytypeleaves=array();
	foreach($typeleaves as $key => $val)
	{
		$labeltoshow = ($langs->trans($val['code'])!=$val['code'] ? $langs->trans($val['code']) : $val['label']);
		//$labeltoshow .= ($val['delay'] > 0 ? ' ('.$langs->trans("NoticePeriod").': '.$val['delay'].' '.$langs->trans("days").')':'');
		$arraytypeleaves[$val['rowid']]=$labeltoshow;
	}
	print $form->selectarray('search_type', $arraytypeleaves, $search_type, 1);
}
print '</td>';

// Duration
print '<td class="liste_titre">&nbsp;</td>';

// Start date
print '<td class="liste_titre center">';
print '<input class="flat valignmiddle" type="text" size="1" maxlength="2" name="search_month_start" value="'.dol_escape_htmltag($search_month_start).'">';
$formother->select_year($search_year_start, 'search_year_start', 1, $min_year, $max_year);
print '</td>';

// End date
print '<td class="liste_titre center">';
print '<input class="flat valignmiddle" type="text" size="1" maxlength="2" name="search_month_end" value="'.dol_escape_htmltag($search_month_end).'">';
$formother->select_year($search_year_end, 'search_year_end', 1, $min_year, $max_year);
print '</td>';

// Status
print '<td class="liste_titre maxwidthonsmartphone maxwidth200 right">';
$holiday->selectStatutCP($search_statut, 'search_statut');
print '</td>';

// Actions
print '<td class="liste_titre maxwidthsearch">';
$searchpicto=$form->showFilterAndCheckAddButtons(0);
print $searchpicto;
print '</td>';

print "</tr>\n";

print '<tr class="liste_titre">';
print_liste_field_titre("Ref", $_SERVER["PHP_SELF"], "cp.ref", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("DateCreateCP", $_SERVER["PHP_SELF"], "cp.date_create", "", $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre("Employee", $_SERVER["PHP_SELF"], "cp.fk_user", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("ValidatorCP", $_SERVER["PHP_SELF"], "cp.fk_validator", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("Type", $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre("NbUseDaysCPShort", $_SERVER["PHP_SELF"], '', '', $pram, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre("DateDebCP", $_SERVER["PHP_SELF"], "cp.date_debut", "", $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre("DateFinCP", $_SERVER["PHP_SELF"], "cp.date_fin", "", $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre("Status", $_SERVER["PHP_SELF"], "cp.statut", "", $param, '', $sortfield, $sortorder, 'right ');
print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
print "</tr>\n";

$listhalfday=array('morning'=>$langs->trans("Morning"),"afternoon"=>$langs->trans("Afternoon"));


// If we ask a dedicated card and not allow to see it, we forc on user.
if ($id && empty($user->rights->holiday->read_all) && ! in_array($id, $childids)) {
	$langs->load("errors");
	print '<tr class="oddeven opacitymediuem"><td colspan="10">'.$langs->trans("NotEnoughPermissions").'</td></tr>';
	$result = 0;
}
elseif (! empty($holiday->holiday) && !empty($mysoc->country_id))
{
    // Lines
    $userstatic = new User($db);
    $approbatorstatic = new User($db);

	$typeleaves=$holiday->getTypes(1, -1);

	foreach($holiday->holiday as $infos_CP)
	{
		// Leave request
		$holidaystatic->id=$infos_CP['rowid'];
		$holidaystatic->ref=($infos_CP['ref']?$infos_CP['ref']:$infos_CP['rowid']);

		// User
		$userstatic->id=$infos_CP['fk_user'];
		$userstatic->lastname=$infos_CP['user_lastname'];
		$userstatic->firstname=$infos_CP['user_firstname'];
		$userstatic->login=$infos_CP['user_login'];
		$userstatic->statut=$infos_CP['user_statut'];
		$userstatic->photo=$infos_CP['user_photo'];

		// Validator
		$approbatorstatic->id=$infos_CP['fk_validator'];
		$approbatorstatic->lastname=$infos_CP['validator_lastname'];
		$approbatorstatic->firstname=$infos_CP['validator_firstname'];
		$approbatorstatic->login=$infos_CP['validator_login'];
		$approbatorstatic->statut=$infos_CP['validator_statut'];
		$approbatorstatic->photo=$infos_CP['validator_photo'];

		$date = $infos_CP['date_create'];

		$starthalfday=($infos_CP['halfday'] == -1 || $infos_CP['halfday'] == 2)?'afternoon':'morning';
		$endhalfday=($infos_CP['halfday'] == 1 || $infos_CP['halfday'] == 2)?'morning':'afternoon';

		print '<tr class="oddeven">';
		print '<td>';
		print $holidaystatic->getNomUrl(1, 1);
		print '</td>';
		print '<td style="text-align: center;">'.dol_print_date($date, 'day').'</td>';
		print '<td>'.$userstatic->getNomUrl(-1, 'leave').'</td>';
		print '<td>'.$approbatorstatic->getNomUrl(-1).'</td>';
		print '<td>';
		$labeltypeleavetoshow = ($langs->trans($typeleaves[$infos_CP['fk_type']]['code'])!=$typeleaves[$infos_CP['fk_type']]['code'] ? $langs->trans($typeleaves[$infos_CP['fk_type']]['code']) : $typeleaves[$infos_CP['fk_type']]['label']);
		print empty($typeleaves[$infos_CP['fk_type']]['label']) ? $langs->trans("TypeWasDisabledOrRemoved", $infos_CP['fk_type']) : $labeltypeleavetoshow;
		print '</td>';
		print '<td class="right">';
		$nbopenedday=num_open_day($infos_CP['date_debut_gmt'], $infos_CP['date_fin_gmt'], 0, 1, $infos_CP['halfday']);
		print $nbopenedday.' '.$langs->trans('DurationDays');
		print '</td>';
		print '<td class="center">';
		print dol_print_date($infos_CP['date_debut'], 'day');
		print ' <span class="opacitymedium">('.$langs->trans($listhalfday[$starthalfday]).')</span>';
		print '</td>';
		print '<td class="center">';
		print dol_print_date($infos_CP['date_fin'], 'day');
		print ' <span class="opacitymedium">('.$langs->trans($listhalfday[$endhalfday]).')</span>';
		print '</td>';
		print '<td class="right">'.$holidaystatic->LibStatut($infos_CP['statut'], 5).'</td>';

	    // Action column
	    print '<td class="nowrap center">';
		if ($massactionbutton || $massaction)   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
	    {
		    $selected=0;
			if (in_array($infos_CP['rowid'], $arrayofselected)) $selected=1;
			print '<input id="cb'.$infos_CP['rowid'].'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$infos_CP['rowid'].'"'.($selected?' checked="checked"':'').'>';
	    }
		print '</td>';

		print '</tr>'."\n";
	}
}

// Si il n'y a pas d'enregistrement suite à une recherche
if ($result == '2')
{
    print '<tr>';
    print '<td colspan="10" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td>';
    print '</tr>';
}

print '</table>';
print '</div>';

print '</form>';

/*if ($user_id == $user->id)
{
	print '<br>';
	print '<div style="float: right; margin-top: 8px;">';
	print '<a href="./card.php?action=request" class="butAction">'.$langs->trans('AddCP').'</a>';
	print '</div>';
}*/

// End of page
llxFooter();
$db->close();





/**
 * Show balance of user
 *
 * @param 	Holiday	$holiday	Object $holiday
 * @param	int		$user_id	User id
 * @return	string				Html code with balance
 */
function showMyBalance($holiday, $user_id)
{
	global $conf, $langs;

	$alltypeleaves=$holiday->getTypes(1, -1);    // To have labels

	$out='';
	$nb_holiday=0;
	$typeleaves=$holiday->getTypes(1, 1);
	foreach($typeleaves as $key => $val)
	{
		$nb_type = $holiday->getCPforUser($user_id, $val['rowid']);
		$nb_holiday += $nb_type;
		$out .= ' - '.$val['label'].': <strong>'.($nb_type?price2num($nb_type):0).'</strong><br>';
	}
	print $langs->trans('SoldeCPUser', round($nb_holiday, 5)).'<br>';
	print $out;
}
