<?php


class WmiBaseClass
{
    public mixed    $ObjectSet;
    public bool     $is_not_COM    =   false;
    public Path_    $Path_;

    /**
     * @param mixed|null $ObjectSet //Com Object - std Object
     * @throws Exception
     */


    public function __construct(mixed $ObjectSet = null)
    {

        $this->is_not_COM = (!property_exists($ObjectSet, 'Properties_') ||
            property_exists($ObjectSet, 'is_json'));

        $this->ObjectSet = $ObjectSet;

        $this->Path_ = new Path_($ObjectSet->Path_);

        if ($ObjectSet->Path_->Class != get_class($this))
            throw new Exception(sprintf("%s not compatible with %s",$ObjectSet->Path_->Class,get_class($this)));


        $reflect = new ReflectionClass($this);
        $reflection = [];
        foreach ($reflect->getProperties() as $property)
            $reflection[$property->getName()] = $property->getType();

        if ($this->is_not_COM)
        {
            foreach ($ObjectSet as $key =>$prop)
            {
                $value = $ObjectSet->{$key};
                if (property_exists($this, $key))
                {
                    switch ($reflection[$key])
                    {
                        case 'array' :
                            $ret_value = $this->ParseArray($value);
                            break;
                        case 'bool' :
                            $ret_value = $this->ParseBoolean($value);
                            break;
                        case 'string' :
                            $ret_value = $this->ParseString($value);
                            break;
                        case 'int' :
                            $ret_value = $this->ParseInt($value);
                            break;
                        case 'Path_' :
                            $ret_value = new Path_($value);
                            break;
                        default:

                            $Enumerator = (string)$reflection[$key];
                            $ret_value=  (in_array($Enumerator, ENUM_CASES, true)) ?
                                $Enumerator::load($value) :
                                ($value ?? "");
                            break;
                    }

                    $this->{$key} = $ret_value;
                }
            }
        }
        else
        {
            foreach ($ObjectSet->Properties_ as $prop)
            {
                $value = $ObjectSet->{$prop->Name};
                if (property_exists($this, $prop->Name))
                {
                    switch ($reflection[$prop->Name])
                    {
                        case 'array' :
                            $ret_value = $this->ParseObject($value);
                            break;
                        case 'bool' :
                            $ret_value = $this->ParseBoolean($value);
                            break;
                        case 'string' :
                            $ret_value = $this->ParseString($value);
                            break;
                        case 'int' :
                            $ret_value = $this->ParseInt($value);
                            break;
                        default:

                            $Enumerator = (string)$reflection[$prop->Name];
                            $ret_value=  (in_array($Enumerator, ENUM_CASES, true)) ?
                                $Enumerator::load($value) :
                                $value ?? "";
                            break;
                    }

                    $this->{$prop->Name} = $ret_value;
                }
            }
        }
    }
    private function ParseObject(mixed $value): array
    {
        if (is_null($value))
            return [];
        $ret = null;
        if (is_object($value))
        {
            foreach ($value as $valor)
            {
                switch (gettype($valor)) {
                    case 'string':
                        $ret[] = $this->ParseString($valor);
                        break;

                    case 'integer':
                        $ret[] = $this->ParseInt($valor);
                        break;

                    case 'boolean':
                        $ret[] = $this->ParseBoolean($valor);
                        break;

                    case 'object':
                        $ret[] = $this->ParseMsvmObject($valor);
                        break;

                    case 'array':
                        $ret[] =  $this->ParseArray($valor);
                        break;

                    default:
                        $ret[] = $valor."(".gettype($value).")";
                }
            }
        }
            else
                $ret[] = ["Failed to load Object Type ".gettype($value)];


        return $ret ?? [];
    }
    private function ParseMsvmObject(mixed $value): mixed
    {
        return $value->Path_->Class::Create($value);
    }
    private function ParseArray(mixed $value): array
    {
        if (is_null($value))
            return [];
        $ret = null;

                foreach ($value as $valor)
                    $ret[] = $valor;


        return $ret ?? [];
    }
    private function ParseBoolean(mixed $value): bool
    {
        if (is_null($value))
            return false;
        return is_bool($value) ? $value : false;
    }
    private function ParseInt(mixed $value): int
    {
        if (is_null($value))
            return -1;
        return is_int($value) ? $value : -1;
    }
    private function ParseString(mixed $value): string
    {
        if (is_null($value))
            return '';
       return  is_string($value) ? $value : gettype($value);
    }
    public function ParseOutParams($outParams):string|bool
    {
        switch ($outParams->ReturnValue ?? 99)
        {
            case 0:
                return 'done';
            case 4096:
                $pattern = '/InstanceID="([A-F0-9\-]{36})"/';
                if (preg_match($pattern, $outParams->Job, $matches)) {
                    return $matches[1];
                } else {
                    return false;
                }
            default : return false;
        }
    }

}
