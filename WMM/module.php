<?php

// Klassendefinition
class WMM extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","WMM");
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyInteger("SourceVariable",0);
		$this->RegisterPropertyInteger("ArchiveId",0);
		$this->RegisterPropertyInteger("MinutesAvg",5);
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableFloat("PowerAvg","Average Power Consumption","~Watt.3680");
		
		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'WMM_RefreshInformation($_IPS[\'TARGET\']);');
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "SourceVariable", "caption" => "Source Variable");
		$form['elements'][] = Array("type" => "SelectModule", "name" => "ArchiveId", "caption" => "Select Archive instance", "moduleID" => "{43192F0B-135B-4CE7-A0A7-1475603F3060}");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "MinutesAvg", "caption" => "Average minutes to be used for power load");
				
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'WMM_RefreshInformation($id);');

		
		// Return the completed form
		return json_encode($form);

	}
	
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
		// Register Variables
		$this->RegisterMessage($this->ReadPropertyInteger("SourceVariable"), VM_UPDATE);
		$this->RegisterReference($this->ReadPropertyInteger("SourceVariable"));

		//Actions
		if (! $this->ReadPropertyBoolean("ReadOnly") ) {
		
			$this->EnableAction("Status");
		}
		else {
			
			$this->DisableAction("Status");
		}

		
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}
	
	// Version 1.0
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		// $logMappings['DEBUG'] 	= 10206; Deactivated the normal debug, because it is not active
		$logMappings['DEBUG'] 	= 10201;
		$logMappings['INFO']	= 10201;
		$logMappings['NOTIFY']	= 10203;
		$logMappings['WARN'] 	= 10204;
		$logMappings['CRIT']	= 10205;
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		parent::LogMessage($messageComplete, $logMappings[$severity]);
	}

	public function RequestAction($Ident, $Value) {
		
		switch ($Ident) {
		
			case "Status":
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				$this->LogMessage("An undefined ident was used","CRIT");
		}
	}
	
	public function RefreshInformation() {
		
		if (! GetValue($this->GetIDForIdent("Status")) ) {
			
			$this->LogMessage("Refresh is inactive because Status is set to off","DEBUG");
			return;
		}

		$this->LogMessage("Refresh in progress","DEBUG");
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(" ; ",$Data), "DEBUG");
		
		$this->RefreshInformation();
	}
	
	private function getAverageValue() {
		
		$arrPower = AC_GetAggregatedValues($this->ReadPropertyInteger("ArchiveId"),$this->ReadPropertyInteger("SourceVariable"),6,time()- $this->ReadPropertyInteger("MinutesAvg") * 60,time(),0);

		$sumPower = 0;

		foreach($arrPower as $currentPower) {

			$sumPower = $sumPower +  $currentPower['Avg'];
		}

		$avgPower = $sumPower / count($arrPower);

		SetValue($this->GetIDForIdent("PowerAvg"), $avgPower);
	}
}
