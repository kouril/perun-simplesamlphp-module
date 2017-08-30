<?php

/**
 * Filter checks whether UID attribute contains @ which means there is a scope.
 * If not then it gets UID, compute hash and construct new eduPersonPrincipalName
 * which consists of [prefix]_[hash(uid)]@[schacHomeOrganization]
 *
 * @author Michal Prochazka <michalp@ics.muni.cz>
 * @author Ondrej Velisek <ondrejvelisek@gmail.com>
 * Date: 21. 11. 2016
 */

class sspmod_perun_Auth_Process_ProcessTargetedID extends SimpleSAML_Auth_ProcessingFilter
{
	private $uidAttr;
	private $prefix;

	public function __construct($config, $reserved)
	{
		parent::__construct($config, $reserved);

		assert('is_array($config)');

		if (!isset($config['uidAttr'])) {
			throw new SimpleSAML_Error_Exception("perun:ProcessTargetedID: missing mandatory configuration option 'uidAttr'.");
		}
		if (!isset($config['prefix'])) {
			throw new SimpleSAML_Error_Exception("perun:ProcessTargetedID: missing mandatory configuration option 'prefix'.");
		}

		$this->uidAttr = (string) $config['uidAttr'];
		$this->prefix = (string) $config['prefix'];
	}

	public function process(&$request)
	{
		assert('is_array($request)');

		if (isset($request['Attributes'][$this->uidAttr][0])) {
			$uid = $request['Attributes'][$this->uidAttr][0];
		} else {
			throw new SimpleSAML_Error_Exception("perun:ProcessTargetedID: " .
					"missing mandatory attribute " . $this->uidAttr . " in request.");
		}

		# Do not continue if we have user id with scope
		if (strpos($uid, '@') !== false) {
			return;
		}

		# Get scope from schacHomeOrganization
		# We are relying on previous module which fills the schacHomeOrganization
		if (isset($request['Attributes']['schacHomeOrganization'][0])) {
			$scope = $request['Attributes']['schacHomeOrganization'][0];
		} else {
			throw new SimpleSAML_Error_Exception("perun:ProcessTargetedID: " .
					"missing mandatory attribute 'schacHomeOrganization' in request.");
		}

		# Generate hash from uid (eduPersonTargetedID)
		$hash = hash('sha256',$uid);

		# Construct new eppn
		$newEduPersonPrincipalName = $this->prefix . '_' . $hash . '@' . $scope;

		SimpleSAML_Logger::info("perun.ProcessTargetedID: Converting eduPersonTargetedID '" . $uid . "' " .
				"to the new ID '" . $newEduPersonPrincipalName . "'");

		# Set attributes back to the response
		# Set uid and also eduPersonPrincipalName, so all the modules and Perun will be happy
		$request['Attributes'][$this->uidAttr] = array($newEduPersonPrincipalName);
		# TODO line below must be removed after ELIXIR will be switched to default behaviour, so Perun will consume original eppn and entityID
		$request['Attributes']['eduPersonPrincipalName'] = array($newEduPersonPrincipalName);

	}

}
