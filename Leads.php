<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  terminal42 gmbh 2011-2012
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class Leads extends Controller
{

	public function __construct()
	{
		parent::__construct();

		$this->import('Database');

		if (FE_USER_LOGGED_IN)
		{
			$this->import('FrontendUser', 'User');
		}
	}


	public function loadBackendModules($arrModules, $blnShowAll)
	{
		$objForms = $this->Database->execute("SELECT *, IF(leadMenuLabel='', title, leadMenuLabel) AS leadMenuLabel FROM tl_form WHERE leadEnabled='1' AND leadMaster=0 ORDER BY leadMenuLabel");

		if ($objForms->numRows)
		{
			$arrSession = $this->Session->get('backend_modules');
			$blnOpen = $arrSession['leads'] || $blnShowAll;

			array_insert($arrModules, 1, array('leads' => array
			(
				'icon'	=> ($blnOpen ? 'modMinus.gif' : 'modPlus.gif'),
				'title'	=> ($blnOpen ? $GLOBALS['TL_LANG']['MSC']['collapseNode'] : $GLOBALS['TL_LANG']['MSC']['expandNode']),
				'label'	=> $GLOBALS['TL_LANG']['MOD']['leads'][0],
				'href'	=> 'contao/main.php?do=leads&amp;master=2&amp;mtg=leads',
				'modules' => array(),
			)));

			if ($blnOpen)
			{
				while ($objForms->next())
				{
					$arrModules['leads']['modules']['leads_'.$objForms->id] = array
					(
						'tables'	=> array(),
						'title'		=> specialchars(sprintf($GLOBALS['TL_LANG']['MOD']['leads'][1], $objForms->title)),
		                'label'		=> $objForms->leadMenuLabel,
		                'icon'		=> 'style="background-image:url(\'system/modules/leads/assets/icon.png\')"',
		                'class'		=> 'navigation leads',
		                'href'		=> 'contao/main.php?do=leads&master='.$objForms->id,
					);
				}
			}
		}

		return $arrModules;
	}


	public function processFormData($arrPost, $arrForm, $arrFiles)
	{
		if ($arrForm['leadEnabled'])
		{
			$time = time();

			$intLead = $this->Database->prepare("INSERT INTO tl_lead (tstamp,created,form_id,master_id,member_id) VALUES (?,?,?,?,?)")
									  ->executeUncached($time, $time, $arrForm['id'], ($arrForm['leadMaster'] ? $arrForm['leadMaster'] : $arrForm['id']), (FE_USER_LOGGED_IN ? $this->User->id : 0))
									  ->insertId;


			// Fetch master form fields
			if ($arrForm['leadMaster'] > 0)
			{
				$objFields = $this->Database->prepare("SELECT f2.*, f1.id AS master_id, f1.name AS postName FROM tl_form_field f1 LEFT JOIN tl_form_field f2 ON f1.leadStore=f2.id WHERE f1.pid=? AND f1.leadStore>0 AND f2.leadStore='1' ORDER BY f2.sorting")->execute($arrForm['id']);
			}
			else
			{
				$objFields = $this->Database->prepare("SELECT *, id AS master_id, name AS postName FROM tl_form_field WHERE pid=? AND leadStore='1' ORDER BY sorting")->execute($arrForm['id']);
			}

			while ($objFields->next())
			{
				if (isset($arrPost[$objFields->postName]))
				{
					$varLabel = '';
					$varValue = $arrPost[$objFields->postName];

					if ($objFields->options != '')
					{
						$arrOptions = deserialize($objFields->options, true);
						$varLabel = $this->prepareLabel($varValue, $arrOptions, $objFields);
					}

					$varValue = $this->prepareValue($varValue, $objFields);

					$arrSet = array
					(
						'pid'			=> $intLead,
						'sorting'		=> $objFields->sorting,
						'tstamp'		=> $time,
						'master_id'		=> $objFields->master_id,
						'field_id'		=> $objFields->id,
						'name'			=> $objFields->name,
						'value'			=> $varValue,
						'label'			=> $varLabel,
					);


					// @todo Trigger hook


					$this->Database->prepare("INSERT INTO tl_lead_data %s")
								   ->set($arrSet)
								   ->executeUncached();
				}
			}
		}
	}


	protected function prepareValue($varValue, $objField)
	{
		// Run for all values in an array
		if (is_array($varValue))
		{
			foreach ($varValue as $k => $v)
			{
				$varValue[$k] = $this->prepareValue($v, $objField);
			}

			return $varValue;
		}

		// Convert date formats into timestamps
		if ($varValue != '' && in_array($objField->rgxp, array('date', 'time', 'datim')))
		{
			$objDate = new Date($varValue, $GLOBALS['TL_CONFIG'][$objField->rgxp . 'Format']);
			$varValue = $objDate->tstamp;
		}

		return $varValue;
	}


	protected function prepareLabel($varValue, $arrOptions, $objField)
	{
		// Run for all values in an array
		if (is_array($varValue))
		{
			foreach ($varValue as $k => $v)
			{
				$varValue[$k] = $this->prepareLabel($v, $arrOptions, $objField);
			}

			return $varValue;
		}

		foreach ($arrOptions as $arrOption)
		{
			if ($arrOption['value'] == $varValue && $arrOption['label'] != '')
			{
				return $arrOption['label'];
			}
		}

		return $varValue;
	}
}

