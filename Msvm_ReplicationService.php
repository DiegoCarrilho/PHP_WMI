<?php

class Msvm_ReplicationService extends WmiBaseServiceClass
{

    /**
     * @param string $username
     * @param string $password
     * @param array  $ServerList
     */
    public function __construct(string $username = '', string $password = '', array $ServerList = [])
    {
        parent::__construct($username, $password,$ServerList);
        $this->namespace = "root\\virtualization\\v2";
        $this->WmiTable = 'Msvm_ReplicationService';

    }

    /**
     * @param ?Msvm_ComputerSystem $ComputerSystem
     * @param string $dstServer
     * @return string|bool
     */
    public function CreateReplicationRelationship(?Msvm_ComputerSystem $ComputerSystem, string $dstServer):string|bool
    {
        if (($dstServer == "") || is_null($ComputerSystem))
            return false;
        $this->ConnectWMI($ComputerSystem->Path_->Server);

        ##TODO select all disks
        $dskQuery = sprintf('SELECT * FROM Msvm_StorageAllocationSettingData WHERE InstanceID LIKE "Microsoft:%s%%"',$ComputerSystem->Name);
        $disk                 = $this->wmiConnection->ExecQuery($dskQuery)->ItemIndex(0);

        $rsdQuery = sprintf('SELECT * FROM Msvm_ReplicationSettingData WHERE VirtualSystemIdentifier LIKE "%s"',$ComputerSystem->Name);
        $rsd                = $this->wmiConnection->ExecQuery($rsdQuery)->ItemIndex(0);


        $rsd->Properties_->Item('AuthenticationType',0)->Value          = "1";
        $rsd->Properties_->Item('IncludedDisks',0)->Value               = [$disk->Path_->Path];
        $rsd->Properties_->Item('PrimaryConnectionPoint',0)->Value      = $ComputerSystem->Path_->Server;
        $rsd->Properties_->Item('PrimaryHostSystem',0)->Value           = $ComputerSystem->Path_->Server;
        $rsd->Properties_->Item('RecoveryConnectionPoint',0)->Value     = $dstServer;
        $rsd->Properties_->Item('RecoveryHostSystem',0)->Value          = $dstServer;
        $rsd->Properties_->Item('RecoveryServerPortNumber',0)->Value    = "80";
        $rsd->Properties_->Item('ReplicationInterval',0)->Value         = "900";

        $inParameters = $this->Service->Methods_->Item('CreateReplicationRelationship')->InParameters->SpawnInstance_();
        $inParameters->ComputerSystem = $ComputerSystem->ObjectSet->Path_->Path;
        $inParameters->ReplicationSettingData = $rsd->GetText_(1);

        $outParams = $this->Service->ExecMethod_('CreateReplicationRelationship', $inParameters,0,null);

        return $this->ParseOutParams($outParams);
    }

    /**
     * @param ?Msvm_ComputerSystem $ComputerSystem
     * @return string|bool
     */
    public function StartReplication(?Msvm_ComputerSystem $ComputerSystem):string|bool
    {
        if (is_null($ComputerSystem))
            return false;
        $this->ConnectWMI($ComputerSystem->Path_->Server);
        $inParams = $this->Service->Methods_->Item('StartReplication')->InParameters->SpawnInstance_();
        $inParams->ComputerSystem           = $ComputerSystem->ObjectSet->Path_->Path;
        $inParams->InitialReplicationType   = 1;
        $outParams = $this->Service->ExecMethod_("StartReplication", $inParams);
        return $this->ParseOutParams($outParams);
    }

    /**
     * @param ?Msvm_ComputerSystem $ComputerSystem
     * @return string|bool
     */
    public function RemoveReplicationRelationship(?Msvm_ComputerSystem $ComputerSystem):string|bool
    {
        if (is_null($ComputerSystem))
            return false;
        $this->ConnectWMI($ComputerSystem->Path_->Server);
        $inParameters = $this->Service->Methods_->Item('RemoveReplicationRelationship')->InParameters->SpawnInstance_();
        $inParameters->ComputerSystem = $ComputerSystem->ObjectSet->Path_->Path;
        $outParams = $this->Service->ExecMethod_('RemoveReplicationRelationship', $inParameters,0,null);
        return $this->ParseOutParams($outParams);
    }

    /**
     * @param ?Msvm_ComputerSystem $ComputerSystem
     * @return Msvm_ReplicationSettingData|bool
     */
    public function GetVMReplicationSettings(?Msvm_ComputerSystem $ComputerSystem):Msvm_ReplicationSettingData|bool
    {
        if (is_null($ComputerSystem))
            return false;
        $this->ConnectWMI($ComputerSystem->Path_->Server);

        $ReplicationSettingDataQuery = sprintf('SELECT * FROM Msvm_ReplicationSettingData WHERE VirtualSystemIdentifier LIKE "%s"',$ComputerSystem->Name);
        $ReplicationSettingData  = $this->wmiConnection->ExecQuery($ReplicationSettingDataQuery);
        if ($ReplicationSettingData->Count != 1)
            return false;
        return Msvm_ReplicationSettingData::Create($ReplicationSettingData->ItemIndex(0));
    }

}
