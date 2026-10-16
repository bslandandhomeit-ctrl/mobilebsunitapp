<?PHP 
namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class MyModels extends Model {


    public function save(array $options=array()) {
        if(config('unified_customer_ui.enabled') && $this->getConnectionName()==='unified_customer_ui') {
            if(!config('unified_customer_ui.writes_enabled'))throw new \LogicException('Unified customer writes disabled.');
            $db=$this->getConnection();$columns=$db->select('SHOW COLUMNS FROM `'.$db->getTablePrefix().$this->getTable().'`');
            foreach($columns as$column){$key=$column->Field;if(array_key_exists($key,$this->attributes)&&$this->attributes[$key]===''&&preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double|date|datetime|timestamp|enum)/i',$column->Type)){
                if($column->Null==='YES')$this->attributes[$key]=null;else throw new \InvalidArgumentException('A value is required for '.$key.'.');
            }}
        }
        return parent::save($options);
    }

    public function getConnection(){return static::resolveConnection($this->getConnectionName());}
    public function getConnectionName()
    {
        $tables=array('clients','customer_profiles','client_cbc_general','client_cbc_address','client_cbc_contact','client_cbc_identification','client_cbc_employer','client_cbc_spouse','cbc_identification_types','economic_cbc_sector');
        if(config('unified_customer_ui.enabled') && (in_array($this->getTable(),$tables) || (config('unified_customer_ui.active') && in_array($this->getTable(),array('save_draft','audit'))))) {
            config(array('database.connections.unified_customer_ui'=>config('unified_customer_ui.database')));
            return 'unified_customer_ui';
        }
        return parent::getConnectionName();
    }

    public $sessionData = false;
    public $lang_id = false;

  function __construct()
  {
      //parent::__construct();
      $this->sessionData = session('users');
      $this->lang_id = $this->sessionData['lang_id'];
  }

  protected function Check_validator($data, $rules) {

      $validator = Validator::make($data, $rules);

      if ($validator->fails()) {

          return $validator->errors();//collect()->collapse()->all();
      }

      return true;

    }
}

?>
