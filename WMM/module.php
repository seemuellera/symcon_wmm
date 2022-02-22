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
		$this->RegisterPropertyFloat("StandbyThreshold",10);
		$this->RegisterPropertyFloat("OffThreshold",1);
		$this->RegisterPropertyInteger("TypicalRuntime",0);
		
		//Attributes
		$this->RegisterAttributeInteger("LastFinish",0);
		$this->RegisterAttributeInteger("LastStart",0);
				
		// Variable profiles
		$variableProfileMachineStatus = "WMM.MachineStatus";
		if (IPS_VariableProfileExists($variableProfileMachineStatus) ) {
		
			IPS_DeleteVariableProfile($variableProfileMachineStatus);
		}			
		IPS_CreateVariableProfile($variableProfileMachineStatus, 1);
		IPS_SetVariableProfileIcon($variableProfileMachineStatus, "Database");
		IPS_SetVariableProfileAssociation($variableProfileMachineStatus, 0, "aus", "Power", -1);
		IPS_SetVariableProfileAssociation($variableProfileMachineStatus, 1, "läuft", "EnergyProduction", 0x00FF00);
		IPS_SetVariableProfileAssociation($variableProfileMachineStatus, 2, "beendet", "Flag", 0xD900FF);
		IPS_SetVariableProfileAssociation($variableProfileMachineStatus, 3, "Standby", "Power", 0xCFCFCF);
		
		$variableProfileRuntime = "WMM.Runtime";
		if (IPS_VariableProfileExists($variableProfileRuntime) ) {
		
			IPS_DeleteVariableProfile($variableProfileRuntime);
		}			
		IPS_CreateVariableProfile($variableProfileRuntime, 1);
		IPS_SetVariableProfileIcon($variableProfileRuntime, "Hourglass");
		IPS_SetVariableProfileText($variableProfileRuntime, "", " min");
		IPS_SetVariableProfileAssociation($variableProfileRuntime, -1, "-", "", -1);
		IPS_SetVariableProfileAssociation($variableProfileRuntime, 1, "%d", "", -1);
		
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableFloat("PowerAvg","Average Power Consumption","~Watt.3680");
		$this->RegisterVariableInteger("MachineStatus","Machine Status",$variableProfileMachineStatus);
		$this->RegisterVariableInteger("MinutesFinished","Minutes since finish",$variableProfileRuntime);
		$this->RegisterVariableInteger("MinutesStarted","Minutes since start",$variableProfileRuntime);
		$this->RegisterVariableInteger("MinutesRemaining","Minutes remaining typically",$variableProfileRuntime);
		$this->RegisterVariableInteger("Progress","Progress","~Intensity.100");
				
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
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "StandbyThreshold", "caption" => "Threshold below which the machine is considered to be in Standby / finished", "digits" => 2);
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "OffThreshold", "caption" => "Threshold below which the machine is considered to be off", "digits" => 2);
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "TypicalRuntime", "caption" => "Typical Runtime for a machine run");
				
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
		
		$this->RegisterReference($this->ReadPropertyInteger("ArchiveId"));

		//Actions
		$this->EnableAction("Status");
				
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
				// Refresh Information if Status was turned on:
				if ($Value) {
					
					$this->RefreshInformation();
				}
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
		
		SetValue($this->GetIDForIdent("PowerAvg"), $this->getAverageValue());
		SetValue($this->GetIDForIdent("MachineStatus"), $this->getMachineStatus());
		SetValue($this->GetIDForIdent("MinutesFinished"), $this->getMinutesSinceFinish());
		SetValue($this->GetIDForIdent("MinutesStarted"), $this->getMinutesSinceStart());
		SetValue($this->GetIDForIdent("MinutesRemaining"), $this->getRemainingMinutes());
		SetValue($this->GetIDForIdent("Progress"), $this->getProgress());

		// More Debug
		$this->LogMessage("Timestamp last start: " . $this->ReadAttributeInteger("LastStart"), "DEBUG");
		$this->LogMessage("Timestamp last finish: " . $this->ReadAttributeInteger("LastFinish"), "DEBUG");
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

		return $avgPower;
	}
	
	private function getMachineStatus() {
		
		$powerAvg = $this->getAverageValue();
		$standbyThreshold = $this->ReadPropertyFloat("StandbyThreshold");
		$offThreshold = $this->ReadPropertyFloat("OffThreshold");
		$oldMachineStatus = GetValue($this->GetIDForIdent("MachineStatus"));
		
		
		if ($powerAvg <= $offThreshold) {
			
			$this->LogMessage("The machine changed to off","DEBUG");
			$this->WriteAttributeInteger("LastFinish", 0);
			$this->WriteAttributeInteger("LastStart", 0);
			return 0;
		}
		
		if ($powerAvg <= $standbyThreshold) {
		
			// If the machine changes from off to below standby we consider it to be in Standby
			if ($oldMachineStatus == 0) {
				
				$this->LogMessage("Machine changed from off to Standby","DEBUG");
				return 3;
			}
			
			// If the machine changes from running to below threshold we consider it to be finished
			if ($oldMachineStatus == 1) {
				
				$this->LogMessage("Machine changed from running to finished","DEBUG");
				if ($this->ReadAttributeInteger("LastFinish") == 0) {
				
					$this->WriteAttributeInteger("LastFinish", time());
				}
				return 2;
			}
	
			// Nothing changed. Keeping old status (either standby or running)
			$this->LogMessage("The machine status has not changed","DEBUG");
			return $oldMachineStatus;
		} 
		
		// machine is running
		$this->LogMessage("Machine is running","DEBUG");
		$this->WriteAttributeInteger("LastFinish", 0);
		if ($this->ReadAttributeInteger("LastStart") == 0) {
		
			$this->WriteAttributeInteger("LastStart", time());
		}
		return 1;
	}
	
	private function getMinutesSinceFinish() {
		
		// return 0 if last finish is not initialized
		if ($this->ReadAttributeInteger("LastFinish") == 0) {
			
			return -1;
		}
		
		$timeDiffSec = time() - $this->ReadAttributeInteger("LastFinish");
		$timeDiffMin = round($timeDiffSec / 60);
		
		return $timeDiffMin;
	}
	
	private function getMinutesSinceStart() {
		
		// return n/a if no start timestamp is set (machine is not in mode running)
		if ($this->ReadAttributeInteger("LastStart") == 0) {
			
			return -1;
		}
		
		$timeDiffSec = time() - $this->ReadAttributeInteger("LastStart");
		$timeDiffMin = round($timeDiffSec / 60);
		
		return $timeDiffMin;
	}
	
	private function getRemainingMinutes() {
		
		// return n/a if the user has not specified a typical runtime
		if ($this->ReadPropertyInteger("TypicalRuntime") == 0) {
			
			return -1;
		}
		
		// return n/a if no start timestamp is set (machine is not in mode running)
		if ($this->ReadAttributeInteger("LastStart") == 0) {
			
			return -1;
		}
		
		$typicalRuntimeSeconds = $this->ReadPropertyInteger("TypicalRuntime") * 60;
		
		$timestampEnd = $this->ReadAttributeInteger("LastStart") + $typicalRuntimeSeconds;
		
		$timeDiffSeconds = $timestampEnd - time();
		
		// return 0 remaining minutes if the difference is negative (run took longer than expected)
		if ($timeDiffSeconds < 0) {
			
			return 0;
		}
		
		$timeDiffMinutes = round($timeDiffSeconds / 60);
		
		return $timeDiffMinutes;
	}
	
	private function getProgress() {
		
		$minutesSinceStart = $this->getMinutesSinceStart();
		
		// return 0 if progress cannot be calculated
		if ($minutesSinceStart == -1) {
			
			return 0;
		}
		
		$typicalMinutes = $this->ReadPropertyInteger("TypicalRuntime");
		
		$progress = round( (100 / $typicalMinutes) * minutesSinceStart, 0);
		
		return $progress;
	}
}
