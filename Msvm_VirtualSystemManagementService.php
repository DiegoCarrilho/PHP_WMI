<?php

class Msvm_VirtualSystemManagementService extends WmiBaseServiceClass
{

    /**
     * @param string $username
     * @param string $password
     * @param array  $ServerList
     */
    public function __construct(string $username = '', string $password = '', array $ServerList = [])
    {
        parent::__construct($username, $password, $ServerList);
        $this->namespace = "root\\virtualization\\v2";
        $this->WmiTable = 'Msvm_VirtualSystemManagementService';
    }

    /**
     * Set ip of a Virtual Machine
     * @param ?Msvm_ComputerSystem $VMObject
     * @param Msvm_GuestNetworkAdapterConfiguration $NetworkAdapterConfiguration
     * @return string|bool
     */
    public function SetGuestNetworkAdapterConfiguration(?Msvm_ComputerSystem $VMObject,Msvm_GuestNetworkAdapterConfiguration $NetworkAdapterConfiguration):string|bool
    {
        if (is_null($VMObject))
            return false;


        $this->ConnectWMI($VMObject->Path_->Server);

        $netAdapterQuery    = sprintf("SELECT * FROM Msvm_GuestNetworkAdapterConfiguration WHERE InstanceID LIKE '%%%s%%'",$VMObject->Name);
        $netAdapterEQ         = $this->wmiConnection->ExecQuery($netAdapterQuery);
        if ($netAdapterEQ->Count != 1)
            return sprintf('Failed to load VM Adapter: (%s)',$VMObject->Name);

        $netAdapter         = $netAdapterEQ->ItemIndex(0);

        if ($NetworkAdapterConfiguration->DHCPEnabled)
        {
                $netAdapter->Properties_->Item('DHCPEnabled',0)->Value      = true;
                $netAdapter->Properties_->Item('IPAddresses',0)->Value      = [];
                $netAdapter->Properties_->Item('Subnets',0)->Value          = [];
                $netAdapter->Properties_->Item('DefaultGateways',0)->Value  = [];
                $netAdapter->Properties_->Item('DNSServers',0)->Value       = [];
        }
        else
        {
            $netAdapter->Properties_->Item('DHCPEnabled',0)->Value      = false;

            if (count($NetworkAdapterConfiguration->IPAddresses) > 0)
                $netAdapter->Properties_->Item('IPAddresses',0)->Value      = $NetworkAdapterConfiguration->IPAddresses;

            if (count($NetworkAdapterConfiguration->Subnets) > 0)
                $netAdapter->Properties_->Item('Subnets',0)->Value          = $NetworkAdapterConfiguration->Subnets;

            if (count($NetworkAdapterConfiguration->DefaultGateways) > 0)
                $netAdapter->Properties_->Item('DefaultGateways',0)->Value  = $NetworkAdapterConfiguration->DefaultGateways;

            if (count($NetworkAdapterConfiguration->DNSServers) > 0)
                $netAdapter->Properties_->Item('DNSServers',0)->Value       = $NetworkAdapterConfiguration->DNSServers;
        }

        $inParameters = $this->Service->Methods_->Item('SetGuestNetworkAdapterConfiguration')->InParameters->SpawnInstance_();
        $inParameters->ComputerSystem = $VMObject->ObjectSet->Path_->Path;
        $inParameters->NetworkConfiguration = [$netAdapter->GetText_(1)];

        $outParams = $this->Service->ExecMethod_('SetGuestNetworkAdapterConfiguration', $inParameters,0,null);

        return $this->ParseOutParams($outParams);

    }

    /**
     * @param ?Msvm_ComputerSystem $VMObject
     * @return ?Msvm_GuestNetworkAdapterConfiguration
     */
    public function GetGuestNetworkAdapterConfiguration(?Msvm_ComputerSystem $VMObject):?Msvm_GuestNetworkAdapterConfiguration
    {
        if (is_null($VMObject))
            return null;
        $this->ConnectWMI($VMObject->Path_->Server);

        $netAdapterQuery    = sprintf("SELECT * FROM Msvm_GuestNetworkAdapterConfiguration WHERE InstanceID LIKE '%%%s%%'",$VMObject->Name);
        $netAdapterEQ         = $this->wmiConnection->ExecQuery($netAdapterQuery);

        if ($netAdapterEQ->Count != 1)
            return null;
        $netAdapter         = $netAdapterEQ->ItemIndex(0);
        return  Msvm_GuestNetworkAdapterConfiguration::Create($netAdapter);
    }

    /**
     * @param ?Msvm_ComputerSystem $VMObject
     * @param string $VLAN_ID 0 to remove VLAN
     * @return string|bool
     */
    public function SetVirtualMachineVLAN(?Msvm_ComputerSystem $VMObject,string $VLAN_ID = '0'):string|bool
    {
        if (is_null($VMObject))
            return false;
        $this->ConnectWMI($VMObject->Path_->Server);

        $EthernetSwitchPortVlanSettingDataQuery = $this->wmiConnection->ExecQuery(
            sprintf("SELECT * FROM Msvm_EthernetSwitchPortVlanSettingData WHERE InstanceID LIKE '%%%s%%'",$VMObject->Name));

        if (!($EthernetSwitchPortVlanSettingDataQuery->Count > 0))
        {

                $syntheticAdapterPath = $this->wmiConnection->ExecQuery(
                    sprintf("SELECT * FROM Msvm_EthernetPortAllocationSettingData WHERE InstanceID LIKE '%%%s%%'", $VMObject->Name))->ItemIndex(0);

                $vlanSettingsData = $this->wmiConnection->Get("Msvm_EthernetSwitchPortVlanSettingData")->SpawnInstance_();
                $vlanSettingsData->AccessVlanId = $VLAN_ID;
                $vlanSettingsData->OperationMode = 1;

                $inParams = $this->Service->Methods_->Item('AddFeatureSettings')->InParameters->SpawnInstance_();
                $inParams->AffectedConfiguration = $syntheticAdapterPath->Path_->Path;
                $inParams->FeatureSettings = [$vlanSettingsData->GetText_(1)];

            $outParams = $this->Service->ExecMethod_("AddFeatureSettings", $inParams);

                return $this->ParseOutParams($outParams);

        }
        else
        {

            $EthernetSwitchPortVlanSettingData = $EthernetSwitchPortVlanSettingDataQuery->ItemIndex(0);

            if ($VLAN_ID > 0)
            {

                $EthernetSwitchPortVlanSettingData->AccessVlanId = $VLAN_ID;

                $inParameters = $this->Service->Methods_->Item('ModifyFeatureSettings')->InParameters->SpawnInstance_();
                $inParameters->FeatureSettings = [$EthernetSwitchPortVlanSettingData->GetText_(1)];

                $outParams = $this->Service->ExecMethod_('ModifyFeatureSettings', $inParameters);

                return $this->ParseOutParams($outParams);
            }
            else
            {
                $FeatureSettingDataQuery = $this->wmiConnection->ExecQuery(
                    sprintf("SELECT * FROM Msvm_FeatureSettingData WHERE InstanceID LIKE '%%%s%%'",$VMObject->Name));
                if ($FeatureSettingDataQuery->Count > 0)
                {
                    $FeatureSettingData = $FeatureSettingDataQuery->ItemIndex(0);
                    $inParameters = $this->Service->Methods_->Item('RemoveFeatureSettings')->InParameters->SpawnInstance_();
                    $inParameters->FeatureSettings = [$FeatureSettingData->Path_->Path];

                    $outParams = $this->Service->ExecMethod_('RemoveFeatureSettings', $inParameters);

                    return $this->ParseOutParams($outParams);

                }
                else
                {
                    return "Not Found";
                }
            }
        }
    }

    /**
     * @param ?string   $VirtualMachineID
     * @param string    $hintServer
     * @param bool      $Running
     * @return Msvm_ComputerSystem|null
     * The function return the first Match only
     */
    public function FindPrimaryVM(?string $VirtualMachineID, string $hintServer, bool $Running = true):?Msvm_ComputerSystem
    {
        if ($VirtualMachineID == "")
            return null;
        $this->SortServerList($hintServer);

        $find_vm_query = sprintf(
            "SELECT * FROM Msvm_ComputerSystem WHERE Name LIKE '%s' AND ReplicationMode <= '%d'%s",
            $VirtualMachineID,
            VM_ReplicationMode::Primary->value,
            $Running ? sprintf(" AND EnabledState = '%d'", VM_EnabledState::Enabled->value) : ''
        );

        return $this->GetVmFromServerListAndQuery($find_vm_query);
    }

    /**
     * @param ?string $VirtualMachineID
     * @param string $hintServer
     * @return Msvm_ComputerSystem|null
     * Find VM by ID in all servers on a array.
     * The function return the first Match only.
     */
    public function FindVM(?string $VirtualMachineID, string $hintServer):?Msvm_ComputerSystem
    {
        if ($VirtualMachineID == '')
            return null;

        $this->SortServerList($hintServer);

        $find_vm_query = sprintf("SELECT * FROM Msvm_ComputerSystem WHERE Name LIKE '%s'",
            $VirtualMachineID);
        return $this->GetVmFromServerListAndQuery($find_vm_query);
    }

    /**
     * @param ?Msvm_ComputerSystem $ComputerSystem
     * @return Msvm_ComputerSystem|null
     * Find VM by ID in all servers on a array.
     * The function return the first Match only.
     */
    public function Get_SummaryInformation(?Msvm_ComputerSystem $ComputerSystem):?Msvm_SummaryInformation
    {
        if (is_null($ComputerSystem))
            return null;

        $this->SortServerList($ComputerSystem->Path_->Server);

        $find_vm_query = sprintf("SELECT * FROM Msvm_SummaryInformation WHERE Name LIKE '%s' AND ReplicationMode < %d",
            $ComputerSystem->Name,
            VM_ReplicationMode::Replica->value);

        foreach ($this->ServerList as $server) {

            $this->ConnectWMI($server);

            $vmEQ = $this->wmiConnection->ExecQuery($find_vm_query);
            if ($vmEQ->Count != 1)
                continue;
            return Msvm_SummaryInformation::Create($vmEQ->ItemIndex(0));
        }
        return null;
    }

    /**
     * @param Msvm_ComputerSystem|null $ComputerSystem
     * @return Msvm_MemorySettingData|null
     */
    public function Get_MemorySettingData(?Msvm_ComputerSystem $ComputerSystem):?Msvm_MemorySettingData
    {
        if (is_null($ComputerSystem))
            return null;

        $this->SortServerList($ComputerSystem->Path_->Server);

        $find_vm_query = sprintf("SELECT * FROM Msvm_MemorySettingData WHERE Caption LIKE 'Memory' AND InstanceID LIKE '%%%s%%'",
            $ComputerSystem->Name);

        foreach ($this->ServerList as $server) {

            $this->ConnectWMI($server);

            $vmEQ = $this->wmiConnection->ExecQuery($find_vm_query);
            if ($vmEQ->Count != 1)
                continue;
            return Msvm_MemorySettingData::Create($vmEQ->ItemIndex(0));
        }
        return null;
    }

    /**
     * @param string $VirtualMachineID
     * @param string $hintServer
     * @return Msvm_ComputerSystem|null
     * The function return the first Match only
     */
    public function FindReplicaVM(string $VirtualMachineID, string $hintServer):?Msvm_ComputerSystem
    {
        if ($VirtualMachineID == '')
            return null;

        $this->SortServerList($hintServer);

        $find_vm_query = sprintf("SELECT * FROM Msvm_ComputerSystem WHERE Name LIKE '%s' AND ReplicationMode = '%s'",
            $VirtualMachineID,
            VM_ReplicationMode::Replica->value);
        return $this->GetVmFromServerListAndQuery($find_vm_query);
    }

    /**
     * @param ?Msvm_ComputerSystem $ComputerSystem
     * @return string|bool
     * Remove a VM based on a self VM Msvm_ComputerSystem
     */
    public function DestroySystem(?Msvm_ComputerSystem $ComputerSystem):string|bool
    {
        if (is_null($ComputerSystem))
            return false;
        $this->ConnectWMI($ComputerSystem->Path_->Server);
        $inParameters = $this->Service->Methods_->Item('DestroySystem')->InParameters->SpawnInstance_();
        $inParameters->AffectedSystem = $ComputerSystem->ObjectSet->Path_->Path;
        $outParams = $this->Service->ExecMethod_('DestroySystem', $inParameters,0,null);
        return $this->ParseOutParams($outParams);
    }

    /**
     * @param ?Msvm_ComputerSystem $ComputerSystem
     * @return string|bool
     * Return the VM Disk Path, used to load a CIM_DataFile class for the VM Hard disk deletion, Must be called before DestroySystem
     * TODO Handle multi disk
     */
    public function GetVMDiskPath(?Msvm_ComputerSystem $ComputerSystem):string|bool
    {
        if (is_null($ComputerSystem))
            return false;
        $this->ConnectWMI($ComputerSystem->Path_->Server);

        $dskQuery = sprintf('SELECT * FROM Msvm_StorageAllocationSettingData WHERE InstanceID LIKE "Microsoft:%s%%"',$ComputerSystem->Name);
        $disk                 = $this->wmiConnection->ExecQuery($dskQuery)->ItemIndex(0);
        return $disk->HostResource[0];

    }

    /**
     * @param string $find_vm_query
     * @return Msvm_ComputerSystem|null
     * Used to iterate on server list returning the first VM match with the Query related
     */
    public function GetVmFromServerListAndQuery(string $find_vm_query): ?Msvm_ComputerSystem
    {
        foreach ($this->ServerList as $server) {

            $this->ConnectWMI($server);

            $vmEQ = $this->wmiConnection->ExecQuery($find_vm_query);
            if ($vmEQ->Count != 1)
                continue;
            return Msvm_ComputerSystem::Create($vmEQ->ItemIndex(0));
        }
        return null;
    }

    /**
     * @param ?Msvm_ComputerSystem $ComputerSystem
     * @param string $NewName
     * @return string|bool
     */
    public function RenameVM(?Msvm_ComputerSystem $ComputerSystem, string $NewName):string|bool
    {
        if (is_null($ComputerSystem))
            return false;
        $this->ConnectWMI($ComputerSystem->Path_->Server);
        $Msvm_VSS_Query = sprintf('SELECT * FROM Msvm_VirtualSystemSettingData WHERE VirtualSystemIdentifier = "%s" AND VirtualSystemType = "Microsoft:Hyper-V:System:Realized"',$ComputerSystem->Name);
        $Msvm_VSS_Res                 = $this->wmiConnection->ExecQuery($Msvm_VSS_Query);
        if ($Msvm_VSS_Res->Count != 1)
            return false;
        $VirtualSystemSettingData = $Msvm_VSS_Res->ItemIndex(0);
        $VirtualSystemSettingData->Properties_->Item('ElementName',0)->Value    = $NewName;
        $inParameters = $this->Service->Methods_->Item('ModifySystemSettings')->InParameters->SpawnInstance_();
        $inParameters->SystemSettings = $VirtualSystemSettingData->GetText_(1);
        return $this->ParseOutParams($this->Service->ExecMethod_('ModifySystemSettings', $inParameters,0,null));
    }

    public function doQuery(string $server, string $query):mixed
    {
        $this->ConnectWMI($server);
        return $this->wmiConnection->ExecQuery($query)->ItemIndex(0);
    }
}
