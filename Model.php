<?php
require 'Inflect.php';
/**
 * Phrap - A Simple PDO Model
 *
 * Handles basic CRUD using PDO, also uses APC to cache metadata.
 * Doesn't handle joins or associations.
 * Should be 5.2.x safe.
 */
class Model
{
    private $db;
    private $apc    = false;
    private $is_new = false;

    private static $id_autoincrement = 0;

    protected $model;
    protected $table;
    protected $field_names;
    protected $virtual_fields = array();

    public $error;
    public $data;

    function __construct($database_connection = null)
    {
        if ($database_connection) {

            $this->model = get_class($this);
        
            // If not explcitly set, then try to guess the table name
            // using the ActiveRecord / Rails pluralization style
            if (!$this->table) {
                if (class_exists('Inflect')) {
                    $this->table = Inflect::pluralize(strtolower($this->model));
                } else {
                    $this->table = strtolower($this->model);
                }
            }

            if (!$this->switch_connection($database_connection)) {
                throw new Exception("Cannot access DB");
            }
        }
    }

    public function switch_connection($database_connection = null)
    {
        if ($this->db = $database_connection->get_connection()) {
            $this->cache_columns(); // cache metadata
            if ($max = $this->get_max_id()) {
                self::$id_autoincrement = (int) $max;
            }
            return true;
        }
        return false;
    }

    /**
     * Models are optimized for APC to cache column_names, otherwise
     * we have to bang the DB on each construct/query.
     *
     * So... USE APC.
     */
    private function cache_columns()
    {
        $apc_check = ini_get('apc.enabled');
        if ($apc_check == 1) {
            $this->apc = true;
            if ($cols = apc_fetch($this->table . '_cols')) {
                $this->field_names = $cols;
            } else {
                $this->field_names = $this->get_columns();
                apc_store($this->table . '_cols', $this->field_names);   
            }
        } else {
            $this->field_names = $this->get_columns();
        }
    }

    private function extract_names($n)
    {
        return $n['column_name'];
    }

    /**
     * Return array of column_names for table
     */
    private function get_columns()
    {
        $sql = "select column_name from information_schema.columns where table_name = '$this->table'";
        $stmt = $this->db->prepare($sql);
        if($stmt->execute()){
            $raw_column_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($raw_column_data) < 1) {
                throw new Exception("Cannot access table $this->table");
            }
        }
        return array_map(array($this, "extract_names"), $raw_column_data);
    }

    /**
     * Add property to object and also allow in Finds.
     * Convenience method for temporary calculated fields.
     */
    public function virtual_field($property = null, $value = null)
    {
        if (!$property || !$value) {
            return false;
        }

        if (!property_exists($this, $property)) {
            $this->$property = $value;
            array_push($this->virtual_fields, $property);
        }

    }

    /**
     * Load object with properties (either from an array or a DB result)
     */
    public function set($data = null)
    {
        if (!$data || !is_array($data)) {
            return false;
        }

        if (isset($data[0]->virtual_fields)) {
            $this->virtual_fields = $data[0]->virtual_fields;
        }

        // If this is a Model object then grab it's properties
        if (isset($data[0]) && is_a($data[0], $this->model)) {
            $data = get_object_vars($data[0]);
        }

        $data = array_intersect_key($data, array_flip(array_merge($this->field_names, $this->virtual_fields))); // filter out non DB field data

        foreach ($data as $property => $value) {
            $this->$property = $value;
        }

        return true;
    }

     /**
     * Removes non-DB related fields from results sets
     */
    private function clean_fields($result, $single = false)
    {
        if ($single) {
            $protected     = array('table', 'db', 'virtual_fields', 'field_names');
            $fields        = array_flip(array_merge($this->field_names, $result->virtual_fields, $protected));
            $object_fields = get_object_vars($result);
            foreach ($object_fields as $property => $val) {
                // Remove null values
                if (is_null($result->$property)) {
                    unset($result->$property);
                }
                // Remove non-db related fields
                if (!isset($fields[$property])) {
                    unset($result->$property);
                }
            }
            return $result;
        } else {
            // Memoize the virtual fields by init-ing the first result if it has
            // an init method.
            $has_init = false;
            if (method_exists($result[0], 'init')) {
                $has_init = true;
                $result[0]->init(); // run any initializers in the models
            }
            $protected     = array('table', 'db', 'virtual_fields', 'field_names');
            $fields        = array_flip(array_merge($this->field_names, $result[0]->virtual_fields, $protected));            
            $object_fields = get_object_vars($result[0]);
            $out           = array();
            $count         = 0;
            foreach ($result as $r) {
                if (($count > 0) && $has_init) {
                    $r->init(); // run any initializers in the model if present
                }
                foreach ($object_fields as $property => $val) {
                    // Remove null values
                    if (isset($r->$property)) {
                        if (is_null($r->$property)) {
                            unset($r->$property);
                        }
                    }
                    if (!isset($fields[$property])) {
                        unset($r->$property);
                    }
                }
                $out[] = $r;
                $count++;
            }
            return $out;
        }
    }

    /**
     * Rudimentary Find function
     * Constructs a SQL query string using AND for conditions.
     * Can find all of a set or just the first.
     */
    public function find($type = null, $conditions = null, $fields = null, $limit = null, $order = null)
    {
        if (!$type) {
            $type = 'first';
        }

        switch ($type) {
            case 'first':
                $limit = 1;
                break;

            case 'all':
                $limit = null;
                break;
            
            default:
                $limit = null;
                break;
        }

        $query = "";
        $query_conditions = "";

        // Build fields
        if (is_array($fields)) {
            $fields = implode(', ', $fields);
        } else {
            $fields = "*";
        }

        // Build conditions
        if (is_array($conditions)) {
            $count = 0;
            $pdo_params = array(); // used to bindParams to PDO statement
            foreach ($conditions as $tbl_field => $tbl_condition) {
                $operator = $this->process_tbl_condition($tbl_condition);

                // Build query string
                $query_conditions .= "$tbl_field {$operator['operator']} :$tbl_field";
                if ($count < count($conditions) - 1) {
                    $query_conditions .= " AND ";
                }

                $pdo_params[':'.$tbl_field] = $operator['condition'];
                $count++;
            }
        }

        $sql = "SELECT $fields FROM $this->table";

        if ($conditions) {
            $sql .=  " WHERE $query_conditions";
        }

        if ($limit) {
            $limit = (int) $limit;
            $sql .=  " LIMIT :limit";
        }

        if ($order) {
            $order = preg_replace('/\W /', '', $order); // strip any nonsense
            $sql .=  " ORDER BY $order";
        }

        $stmt = $this->db->prepare($sql);

        // Build PDO params
        if ($conditions) {
            foreach ($pdo_params as $field => &$value) {
                $stmt->bindParam($field, $value, PDO::PARAM_STR);
            }
        }
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }

        $stmt->execute();

        /**
         * For a single record we grab vanilla Objects and
         * then load the fields into our model
         */
        if ($type == 'first') {
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            if ($stmt->rowCount() === 0) {
                return false;
            } 

            $fields = get_object_vars($result);
            foreach ($fields as $prop => $value) {
                $this->$prop = $value;
            }

            if (method_exists($this, 'init')) {
                $this->init(); // run any initializers in the models
            }
            return $this->clean_fields($this, true);
        }

        $result = $stmt->fetchAll(PDO::FETCH_CLASS, $this->model);
        return $this->clean_fields($result);
    }

    /**
     * Do a raw SQL query with option of parameterized values (please use parameterized query)
     */
    public function query($sql = null, $values = null, $return = true)
    {
        if (!$sql || !$values) {
            return false;
        }

        $stmt = $this->db->prepare($sql);

        /**
         * If values is an array then we use names parameters (:value = value).
         * If values is just a string/integer then we expect question mark
         * placeholders ('SELECT name, colour, calories FROM fruit WHERE calories < ?')
         */
        if (is_array($values)) {
            foreach ($values as $field => &$value) {
                $param_type = PDO::PARAM_STR;
                if (is_int($value)) {
                    $param_type = PDO::PARAM_INT;
                }
                $stmt->bindParam(':'.$field, $value, $param_type);
            }
        } else {
            $param_type = PDO::PARAM_STR;
            if (is_int($values)) {
                $param_type = PDO::PARAM_INT;
            }
            $stmt->bindParam(1, $values, $param_type);
        }
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            if ($return) {
                $result = $stmt->fetchAll(PDO::FETCH_CLASS, $this->model);
                return $this->clean_fields($result);
            }
            return true;
        }
        return false;
    }

    /**
     * Update database row base on condition
     * $condition = array()
     * $data = array()
     */
    public function update($condition = null, $data = null)
    {
        if (!$condition || !$data) {
            return false;
        }

        $this->is_new = false;

        $sql = "UPDATE $this->table SET ";

        $execute = array(); // Build array of named params for PDO

        $count = 0;
        foreach ($data as $field => $value) {
            $operator = $this->process_tbl_condition($value);
            // Build query string
            $sql .= "$field{$operator['operator']}:$field";
            if ($count < count($data) - 1) {
                $sql .= ", ";
            }
            $count++;
            $execute[':'.$field] = $value;
        }

        foreach ($condition as $cfield => $cvalue) {
            $sql .= " WHERE $cfield=:$cfield";
            $execute[':'.$cfield] = $cvalue;
        }

        $stmt = $this->db->prepare($sql);
        try {
            if ($stmt->execute($execute)) {
                if ($stmt->rowCount() > 0) {
                  return true;
                }
                $this->error = "Could not update non-existant record";
                return false;
            }
        } catch (Exception $e) {
            $this->error = $e->errorInfo[2];
            return false;
        }
    }

    /**
     * Insert record into DB and retun ID on success
     */
    public function insert($data = null)
    {
        if (!$data) {
            return false;
        }
        
        $this->is_new = false;

        $execute = array(); // Build array of named params for PDO
        $insert_fieldnames = "";
        $insert_paramnames = "";
        $count = 0;
        foreach ($data as $field => $value) {
             // Build query string
            $insert_fieldnames .= "$field";
            $insert_paramnames .= ":$field";
            if ($count < count($data) - 1) {
                $insert_fieldnames .= ", ";
                $insert_paramnames .= ", ";
            }
            $count++;

            $execute[':'.$field] = $value;
        }

        $sql = "INSERT INTO $this->table ($insert_fieldnames) VALUE ($insert_paramnames)";
        $stmt = $this->db->prepare($sql);
        try {
            if ($stmt->execute($execute)) {
                if ($stmt->rowCount() > 0) {
                    self::$id_autoincrement = $this->db->lastInsertId();
                    return self::$id_autoincrement;
                }
                return false;
            }
        } catch (Exception $e) {
            $this->error = $e->errorInfo[2];
            return false;
        }
        return false;
    }

    /**
     * Persist data to DB
     * If an id is set, then we update, else we create a new record and return id
     */
    public function save()
    {
        $data = get_object_vars($this);
        $data = array_intersect_key($data, array_flip($this->field_names)); // filter out non DB field data
        $data = array_filter($data, array($this, "filter_null"));

        if (isset($this->id) && $this->is_new === false) {
            return $this->update(array('id' => $this->id), $data);
        } else {
            $this->is_new = false;
            return $this->insert($data);
        }
    }

    /**
     * Set the next id on the object
     */
    public function create()
    {
        $this->delete_properties();
        self::$id_autoincrement++;
        $this->id     = self::$id_autoincrement;
        $this->is_new = true;
        return $this;
    }

    /**
     * Delete a record
     */
    public function delete($id = null)
    {
        if (!$id) {
            if ($this->id) {
                $id = $this->id;
            } else {
                return false;
            }
        }

        $sql = "DELETE FROM $this->table WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':id' => $id));
        if ($stmt->rowCount() > 0) {
            $this->delete_properties();
            return true;
        }
        return false;
    }

    /**
     * Wipe out object properties
     */
    private function delete_properties()
    {
        foreach ($this->field_names as $property => $value) {
            $this->$value = null;
        }

        if (isset($this->virtual_fields)) {
            foreach ($this->virtual_fields as $key => $property) {
                $this->$property = null;
            }
        }
    }

    /**
     * Determine the highest ID in table
     */
    private function get_max_id()
    {
        $sql = "SELECT MAX(id) FROM $this->table";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        if (isset($result[0])) {
            return $result[0];
        }
        return false;
    }

    /**
     * Used with array_filter to remove NULL values from array
     * but still allow FALSE (ex: '') values
     */
    private function filter_null($n)
    {
        if (!is_null($n)) {
            return true;
        }
        return false;
    }

    /**
     * Check to see if a string has SQL operators (=, <, >, etc)
     * and return an array of operator + condition
     */
    public function process_tbl_condition($condition)
    {
        if (stripos($condition, ' ') === false) {
            return array('operator' => '=', 'condition' => $condition);   
        }
        $pieces = explode(' ', $condition);
        return array('operator' => $pieces[0], 'condition' => $pieces[1]);              
    }
}
?>