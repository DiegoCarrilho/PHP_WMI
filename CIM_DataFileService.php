<?php


class CIM_DataFileService extends WmiBaseServiceClass
{
    public function __construct(string $username = '', string $password = '', array $ServerList = [])
    {
        parent::__construct($username, $password,$ServerList);
        $this->namespace = "root\\cimv2";
    }


    /**
     * @param string $FilePath
     * @param string $targetServer
     * @return CIM_DataFile|null
     * @throws Exception
     */
    public function SelectFile(string $FilePath, string $targetServer):?CIM_DataFile
    {
        $this->ConnectWMI($targetServer);
        $this->Service = $this->wmiConnection->ExecQuery(sprintf("SELECT * FROM CIM_Datafile WHERE Name = '%s'",addslashes($FilePath)));
        return ($this->Service->Count == 1) ? new CIM_DataFile($this->Service->ItemIndex(0)): null;
    }

}
