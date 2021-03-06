<?php
namespace core\models;
/**
   Базовая модель
   Простейшая реализация ORM
   создание возможно таблиц только через субд!
   название класса - таблица в базе данных
*
*/
class BaseModel {
    private static $pdo;

    public function __construct()
    {
        $this->table_name = preg_replace('#.*\\\#', '', get_class($this));
        $this->table_name = strtolower($this->table_name);

        $this->values = [];
        $this->empty_fields = [];
        $this->errors = false;
        return $this;
    }

    public static function plug_pdo($pdo)
    {
        self::$pdo = $pdo;
    }

    public function get_objects(array $foreign_colums = [])
    { 
        if ($foreign_colums) {
            $this->foreign_colums = $foreign_colums;
            $this->query = $this->foreign_colums_query();
        } else {
            $this->query = "SELECT * FROM $this->table_name";
        }

        return $this;
    }

    public function get_object(string $id, array $foreign_colums = [])
    { 
        if ($foreign_colums) {
            $this->foreign_colums = $foreign_colums;
            $this->query = $this->foreign_colums_query();
        } else { 
            $this->query = "SELECT * FROM $this->table_name";
        }

        $this->query .= " WHERE {$this->table_name}.id = ?";
        $this->values[] = $id;

         // выполняем запрос
        $statement = self::$pdo->prepare($this->query);
        $statement->execute($this->values);

        $object = $statement->fetch();

        return $object;
    }

    public function get_columns(array $columns)
    { 
        $columns = implode(', ', $columns);
        $this->query = "SELECT $columns FROM $this->table_name";
        
        return $this;
    }

    public function get_column__make_query($column)
    {
        $query = "SELECT $column FROM $this->table_name";

        // выполняем запрос
        $statement = self::$pdo->prepare($query);
        $statement->execute();
        return $statement->fetchAll(); // получаем значение
    }

    public function add_object(array $fields) 
    {
        // получаем значения и ключи списка и преобразуем в sql запрос
        if (!$this->errors) { // проверяем
            $keys = array_keys($fields);
            $keys = implode(', ', $keys);
            $this->values = array_values($fields);
            $questions_signs_array = array_fill(0, count($this->values), '?');
            $query_questions_signs_for_pdo = implode(', ', $questions_signs_array );
            $this->query = "INSERT INTO $this->table_name ($keys) VALUES ( $query_questions_signs_for_pdo )";
             // выполняем запрос
            $statement = self::$pdo->prepare($this->query);
            $statement->execute($this->values);
        }
    }

    public function edit_object($fields, $id) // не работает 
    {   
        // получаем значения и ключи списка и преобразуем в sql запрос
        if (!$this->errors) { // проверяем
            $keys = array_keys($fields);
            $this->values = array_values($fields);

            $set_keysNvalues = '';
            $count = count($keys);
            for ($i=0; $i < $count; $i++) {
                $set_keysNvalues .= " {$keys[$i]} = ?";
                if ($i + 1 < $count) {
                    $set_keysNvalues .= ","; // не добавляем, если последняя итерация
                }
            }

            $this->query = "UPDATE $this->table_name SET $set_keysNvalues WHERE id = $id";
             // выполняем запрос
            $statement = self::$pdo->prepare($this->query);
            $statement->execute($this->values);
        }
    }

    public function del_object($id)
    {
        $this->query = "DELETE FROM $this->table_name WHERE id = ?";
        $this->values = [$id];
        // выполняем запрос
        $statement = self::$pdo->prepare($this->query);
        $statement->execute($this->values);
    }

    public function del_objects($ids)
    {
        $questions_signs_array = array_fill(0, count($ids), '?');
        $query_questions_signs_for_pdo = implode(', ', $questions_signs_array );

        if ($ids) {
            $this->query = "DELETE FROM $this->table_name WHERE id IN ( $query_questions_signs_for_pdo )";
            $this->values = $ids;
            // выполняем запрос
            $statement = self::$pdo->prepare($this->query);
            $statement->execute($this->values);       
        }
           
    }

    public function get_total_count__make_query()
    {
        $query = "SELECT COUNT(id) FROM $this->table_name";

        // выполняем запрос
        $statement = self::$pdo->prepare($query);
        $statement->execute();
        return $statement->fetchColumn(); // получаем значение

    }

    public function get_current_count__make_query()
    {
        $query = preg_replace("#SELECT(.)+FROM#", "SELECT COUNT({$this->table_name}.id) FROM", $this->query); // опасно

        // выполняем запрос
        $statement = self::$pdo->prepare($query);
        $statement->execute($this->values);
        return $statement->fetchColumn(); // получаем значение

    }

    public function describe_table__make_query()
    {
        $query = "DESCRIBE $this->table_name";

        // выполняем запрос
        $statement = self::$pdo->prepare($query);
        $statement->execute($this->values);
        return $statement->fetchAll(); // получаем значение
    }

    public function filter($column, string $sign, $value)
    {   
        $sign = strtoupper($sign);
        $column = str_replace(' ', '', $column);
        $comparisons = ['<', '<=', '>', '>=', '='];

        if (in_array($sign, $comparisons) && (is_numeric($value) || $sign == '=')) {

            if (is_numeric($value)) $value = intval($value); // чтобы сравнение проиходило по числу, а не по строке

            if (strpos($this->query, ' WHERE ') === false) { // если where не применялось
                $this->query .= " WHERE {$this->table_name}.{$column} "; // добавляем
                $condition = "$sign ?";
            } else {
                $condition = " AND {$this->table_name}.{$column} $sign ?"; // если применялось, то добавляем AND
            }
            
            $this->query .= $condition;
            $this->values[] = $value;

        } elseif ($sign == 'IN') {
            $questions_signs_array = array_fill(0, count($value), '?');
            $query_questions_signs_for_pdo = implode(', ', $questions_signs_array );

            if (strpos($this->query, ' WHERE ') === false) { // если where не применялось
                $this->query .= " WHERE {$this->table_name}.{$column} "; // добавляем
                $condition = "$sign ( $query_questions_signs_for_pdo )";
            } else {
                $condition = " AND {$this->table_name}.{$column} $sign ( $query_questions_signs_for_pdo )"; // если применялось, то добавляем AND
            }

            $this->query .= $condition;
            $this->values = array_merge($this->values, $value);
        } elseif (!in_array($sign, $comparisons) && !$sign == 'IN') {
            throw new \Exception("Непоняный символ или неправильно применен.");
        }

        return $this;
    }

    public function limit($LIMIT, $LIMIT_COUNT = false)
    {
        if (is_numeric($LIMIT) && isset($this->query)) {
            $this->query .= " LIMIT " . (int)$LIMIT;
            if ($LIMIT_COUNT !== false && is_numeric($LIMIT_COUNT)) $this->query .= ", " . (int)$LIMIT_COUNT;
        } else {
            throw new \Exception("Error Processing Request");
            
        }
        return $this;
    }

    public function order_by($field, $method = 'ASC')
    {
        if (strrpos(' ', $field) === false) { // если нет пробелов в названии
            if ($method == 'ASC' && isset($this->query)){
                $this->query .= " ORDER BY $field ASC";
            } elseif ($method == 'DESC') {
                $this->query .= " ORDER BY $field DESC";
            }
        } else {
            throw new \Exception("В названии поля содержатся пробелы");
        }
        return $this;
    }

    public function make_query()
    {   
        // выполняем запрос
        $statement = self::$pdo->prepare($this->query);
        $statement->execute($this->values);
        return $statement->fetchAll();
    }

    private function foreign_colums_query()  // test. dangerous. sql injections
    {
        // получаем значения для запроса
        $keys = '';
        $concat_tables = '';
        foreach ($this->foreign_colums as $key => $value) { // key - название импортируемое таблицы
            foreach ($value as $column_name) {
                $keys .= ", {$key}.{$column_name} AS {$key}_{$column_name}";
            }
            // {название импортируемое таблицы}.{ее id} = {название таблицы}.{название импортируемое таблицы} 
            $concat_tables .= " LEFT OUTER JOIN $key ON {$key}.id = {$this->table_name}.{$key}"; 
        }
        $query = "SELECT product.* $keys FROM $this->table_name $concat_tables";
        return $query;
    }

}
