<?php
/**
 * Easyform PHP Library.
 * Copyright (C) 2014 Alexander Forselius <drsounds@gmail.com>
 * License: MIT 
 */
 

class Easyform {
	/**
	 * Table
	 */
	public $table;
	/**
	 * PDO Connection
	 */
	public $conn;
	
	/**
	 * ID Field
	 */
	public $idField;
	
	public $fieldsets;
	
	/**
	 * Creates a new QForm
	 */
	public function __construct($table, $fieldsets, $conn, $idField = 'id') {
		$this->table = $table;
		$this->conn = $conn;
		$this->idField = $idField;
		$this->fieldsets = $fieldsets;
	}
	public function table($conditions = array(), $order = array()) {
		$page = $_GET['p'];
		if (!$page) {
			$page = 0;
		}
		$pagesize = $_GET['pagesize'];
		if (!$pagesize) {
			$pagesize = 25;
		}
		$sql = "SELECT ";
		$fields = array($this->table . '.id AS id');
		$i = 0;
		foreach($this->fieldsets as $fieldset) {
			foreach($fieldset['fields'] as $field) {
				if ($field['type'] == 'related') {

					$fields[] = " a" . $i . "." . $field['title_field'] . " AS a" . $i . "_title";
					$i++;
				} else {
					$fields[] = $this->table . "." . $field['name'];
				}
			}

		}

		$sql .= implode(', ', $fields) . " FROM " . $this->table . ' ';
				$i = 0;
		foreach($this->fieldsets as $fieldset) {
			foreach($fieldset['fields'] as $field) {
				if ($field['type'] == 'related') {

					$sql .= " LEFT JOIN " . $field['table'] . " a" . $i . " ON a" . $i . "." . $field['id_field'] 	. " = " . $this->table . "." . $field['name'];
					$i++;
				}
			}

		}
		$sql .= " WHERE TRUE ";
		foreach($conditions as $k => $v) {
			$sql .= " AND " . $k . " = :" . $k . " ";
		}
		if (count($order) > 0) {
			$sql .= " ORDER BY ";
			foreach($order as $k => $v) {
				$sql .= $k . " " . $v;
			}
		}
		$sql .= " LIMIT " .($page * $pagesize)  . "," .  ($pagesize ) ;
		$q = $this->conn->prepare($sql);
		$q->execute($conditions) or die(var_dump($q->errorInfo() ));

		$rows = $q->fetchAll();
		?><table class="table table-bordered">
			<thead>
				<tr>
					<?php
					foreach($this->fieldsets as $fieldset):
						foreach($fieldset['fields'] as $field):?>
					<th><?php echo $field['title']?></th>
				<?php endforeach;?>
				<th></th>
				<th></th>
				</tr>
				<?php endforeach;?>
			</thead>
			<tbody>
				<?php
				foreach($rows as $row):?>
				<tr>
					<?php
						$i = 0;
					foreach($this->fieldsets as $fieldset):
					foreach($fieldset['fields'] as $field):?><td><?php
						switch ($field['type']) {
						case 'related':

						?>
							<?php echo $row['a' . $i . '_' .$field['title_field']]?>
						<?php
						break;
						default:?>
							<?php echo substr($row[$field['name']], 0, 100)?>
						<?php break;
						}	
						$i++;		
					endforeach;?></td><?php
					endforeach;?>
					<td><a href="?view=edit&amp;id=<?php echo $row['id']?>" class="btn btn-warning">Edit</a></td>
					<td><a href="?view=delete&amp;id=<?php echo $row['id']?>" class="btn btn-danger">Delete</a></td>
					
				</tr>
				<?php
				endforeach;?>
			</tbody>
		</table><?php
					
	}
	/**
	 * Renders a form, if id is null create new else edit existing
	 */
	public function render($action, $id = NULL) {
	
		$fieldsets = $this->fieldsets;

		$data = array();
		?><form method="POST" action="<?php echo $action?>?view=edit&amp;<?php echo $id != NULL ? 'id=' . $id . '' : ''?>">
			<?php if ($id != NULL):
				$q = $this->conn->prepare("SELECT * FROM " . $this->table . " WHERE " . $this->idField . " = :id") or die(var_dump($this->conn->errorInfo()));;
				$q->execute(array('id' => $id)) or die(var_dump($q->errorInfo()));
				$data = $q->fetch();
				
				?>
				<input name="id" type="hidden" value="<?php echo $id?>">
			<?php endif;?>
		<?php foreach($fieldsets as $fieldset):?>
			<fieldset>
				<legend><?php echo $fieldset['legend']?></legend>
				<?php foreach($fieldset['fields'] as $field):?>
			<label for="<?php echo $field['name']?>"><?php echo $field['title']?></label>
			<?php
			switch($field['type']) {
				case "html":?>
				<textarea name="<?php echo $field['name']?>" placeholder="<?php echo $field['placeholder']?>"><?php echo @$data[$field['name']]?></textarea>
				<script type="text/javascript">
				tinymce.init({
				    selector: "textarea"
				 });
				</script><?php
					break;

				case "textarea":
					?><textarea class="form-control" name="<?php echo $field['name']?>" placeholder="<?php echo $field['placeholder']?>"><?php echo @$data[$field['name']]?></textarea>
					<?php
					break;
				case "datetime":?>
					<input class="form-control" value="<?php echo isset($data[$field['name']]) ? $data[$field['name']] : date('Y-m-d H:i:s')?>" name="<?php echo $field['name']?>" value="<?php echo @$data[$field['name']]?>" placeholder="<?php echo @$field['placeholder']?>"><?php
					break;
				default:?>
			<input class="form-control" name="<?php echo $field['name']?>" value="<?php echo @$data[$field['name']]?>" placeholder="<?php echo @$field['placeholder']?>">
				<?php
				break; 
				case "related":
					$q = $this->conn->prepare("SELECT * FROM " . $field['table'] . "");
					$q->execute();
					$objects = $q->fetchAll();
				?><select class="form-control" name="<?php echo $field['name']?>">
					<option value="-1">#No alternative#</option>
					<?php foreach($objects as $object):?>
					<option <?php if($object[$field['id_field']] == @$data[$field['name']]) echo "selected";?> value="<?php echo $object[$field['id_field']]?>"><?php echo $object[$field['title_field']]?></option>
					<?php endforeach;?>
					</select><?php
					break;
				case "file":
					?><input type="file" name="<?php echo $field['name']?>"><?php
					break;
			}
			
			endforeach;?>
			</fieldset>
			<?php endforeach;?>
		<button class="btn btn-primary" type="submit">Submit</button>
		</form><?php	
	}
	
	/***
	 * Submits a form
	 */
	public function submit() {
		
		if (isset($_POST['id'])) {
			// If we submit a change to a existing item
			$sql = "UPDATE " . $this->table . " SET ";
			$values = array();
			$_values = array();
			foreach($this->fieldsets as $fieldset) {
				foreach($fieldset['fields'] as $field) {
					$values[$field['name']] = $_POST[$field['name']];
					$_values[] = $field['name'] . " = :" . $field['name'];
				}
			}
				
			$sql .= implode(' , ', $_values);
			$sql .= " WHERE " . $this->idField . " = :id";
			$values['id'] = $_POST['id'];
			$q = $this->conn->prepare($sql);
		
			$q->execute($values) or die(var_dump($q->errorInfo()));
			return $_POST['id'];
		} else {
			$sql = "INSERT INTO " . $this->table . " (";
			$_fields = array();
			$values = array();
			$_f = array();
			foreach($this->fieldsets as $fieldset) {
			
				foreach($fieldset['fields'] as $field) {
					$_fields[] = ":" . $field['name'];
					$_f[] = $field['name'];
					$values[$field['name']] = $_POST[$field['name']];
				}
			}
			$sql .= implode(',', $_f);
			$sql .= ") VALUES (";
			$sql .= implode(', ', $_fields);
			$sql .= ")";
			$q = $this->conn->prepare($sql);
			$q->execute($values);
			return $this->conn->lastInsertId();
			
				
		}
	}
}