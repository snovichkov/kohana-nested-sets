<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Kohana ORM Nested Sets
 * Based on Doctrine_Node_NestedSet
 *
 * @package    CMS.Core
 * @author     Novichkov Sergey(Radik) <novichkovsergey@yandex.ru>
 * @copyright  Copyrights (c) 2011 Novichkov Sergey
 *
 * @property  integer $id
 * @property  integer $lft
 * @property  integer $rgt
 * @property  integer $level
 * @property  integer $scope
 */
abstract class Kohana_ORM_Nested_Sets extends ORM{

    /**
     * Left column name
     *
     * @var string
     */
    protected $left_column = 'lft';

    /**
     * Rigth column name
     *
     * @var string
     */
    protected $right_column = 'rgt';

    /**
     * Level column name
     *
     * @var string
     */
    protected $level_column = 'level';

    /**
     * Scope column name
     *
     * @var string
     */
    protected $scope_column = 'scope';

    /**
     * Use or not scope for multi root's tree
     *
     * @var bool
     */
    protected $use_scope = TRUE;

    /**
     * Test if node has previous sibling
     *
     * @return bool
     */
    public function has_prev_sibling()
    {
        return $this->is_valid_node($this->get_prev_sibling());
    }

    /**
     * Test if node has next sibling
     *
     * @return bool
     */
    public function has_next_sibling()
    {
        return $this->is_valid_node($this->get_next_sibling());
    }

    /**
     * Test if node has children
     *
     * @return bool
     */
    public function has_children()
    {
        return (($this->{$this->right_column} - $this->{$this->left_column}) > 1);
    }

    /**
     * Test if node has parent
     *
     * @return bool
     */
    public function has_parent()
    {
        return $this->is_valid_node() && !$this->is_root();
    }

    /**
     * Gets record of prev sibling or empty record
     *
     * @return Kohana_ORM_Nested_Sets
     */
    public function get_prev_sibling()
    {
        return $this->and_scope(
            self::factory($this->object_name())
                ->where($this->right_column, '=', $this->{$this->left_column} - 1)
        )->find();
    }

    /**
     * Gets record of next sibling or empty record
     *
     * @return Kohana_ORM_Nested_Sets
     */
    public function get_next_sibling()
    {
        return $this->scope(
            self::factory($this->object_name())
                ->where($this->left_column, '=', $this->{$this->right_column} + 1)
        )->find();
    }

    /**
     * Gets siblings for node
     *
     * @todo Optimize
     *
     * @param bool $include_node
     *
     * @return array array of sibling Kohana_ORM_Nested_Sets objects
     */
    public function get_siblings($include_node = FALSE)
    {
        $siblings = array();
        $parent = $this->get_parent();
        if ($parent && $parent->loaded())
        {
            foreach ($parent->get_children() as $child)
            {
                if ($this->is_equal_to($child) && !$include_node)
                {
                    continue;
                }

                $siblings[] = $child;
            }
        }

        return $siblings;
    }

    /**
     * Gets record of first child or empty record
     *
     * @return Kohana_ORM_Nested_Sets
     */
    public function get_first_child()
    {
        return $this->and_scope(
            self::factory(self::object_name())
                ->where($this->left_column, '=', $this->{$this->left_column} + 1)
        )->find();
    }

    /**
     * Gets record of last child or empty record
     *
     * @return Kohana_ORM_Nested_Sets
     */
    public function get_last_child()
    {
        return $this->and_scope(
            self::factory(self::object_name())
                ->where($this->right_column, '=', $this->{$this->right_column} - 1)
        )->find();
    }

    /**
     * Gets children for node (direct descendants only)
     *
     * @todo Check
     *
     * @return mixed  The children of the node or FALSE if the node has no children.
     */
    public function get_children()
    {
        return $this->get_descendants(1);
    }

    /**
     * Gets descendants for node (direct descendants only)
     *
     * @param null $depth
     * @param bool $include_node    Include or not this node default false
     *
     * @return mixed  The descendants of the node or FALSE if the node has no descendants.
     */
    public function get_descendants($depth = null, $include_node = FALSE)
    {
        /** @var ORM $result **/
        $result = $this->and_scope(
            self::factory(self::object_name())
                ->where($this->left_column, $include_node ? '>=' : '>', $this->{$this->left_column})
                ->and_where($this->right_column, $include_node ? '<=' : '<', $this->{$this->right_column})
        )->order_by($this->left_column, 'ASC');

        if ($depth !== null)
        {
            $result->and_where($this->level_column, '<=', $this->{$this->level_column} + $depth);
        }

        $result = $result->find_all();

        return $result->count() > 0 ? $result : FALSE;
    }

    /**
     * Gets record of parent or empty record
     *
     * @return Kohana_ORM_Nested_Sets
     */
    public function get_parent()
    {
        return $this->and_scope(
            self::factory(self::object_name())
                ->where($this->left_column, '<', $this->{$this->left_column})
                ->and_where($this->right_column, '>', $this->{$this->right_column})
                ->and_where($this->level_column, '>=', $this->{$this->level_column} - 1)
        )->order_by($this->right_column, 'asc')->find();
    }

    /**
     * Gets ancestors for node
     *
     * @param null $depth
     *
     * @return mixed   The ancestors of the node or FALSE if the node has no ancestors (this basically means it's a root node).
     */
    public function get_ancestors($depth = null)
    {
        /** @var ORM $result **/
        $result = $this->and_scope(
            self::factory(self::object_name())
                ->where($this->left_column, '<', $this->{$this->left_column})
                ->and_where($this->right_column, '>', $this->{$this->right_column})
        )->order_by($this->left_column, 'asc');

        if ($depth !== null)
        {
            $result->and_where($this->level_column, '>=', $this->{$this->level_column} - $depth);
        }

        $result = $result->find_all();

        return $result->count() > 0 ? $result : FALSE;
    }

    /**
     * Gets path to node from root, uses record::toString() method to get node names
     *
     * @param   bool    $include_root Include or not in path root node
     * @param   bool    $include_self Include or not in path self node
     *
     * @return  array
     */
    public function get_path($include_root = FALSE, $include_self = FALSE)
    {
        $path = array();

        $ancestors = $this->get_ancestors();
        if ($ancestors)
        {
            foreach ($ancestors as $ancestor)
            {
                if (! $include_root)
                {
                    $include_root = TRUE;
                    continue;
                }

                $path[] = $ancestor;
            }
        }

        if ($this->is_root() AND !$include_root)
        {
            return $path;
        }

        // add self node
        if ($include_self AND $this->loaded())
        {
            $path[] = $this;
        }

        return $path;
    }

    /**
     * Gets number of children (direct descendants)
     *
     * @return int
     */
    public function get_number_children()
    {
        /** @var Database_Result $children **/
        $children = $this->get_children();
        return $children === FALSE ? 0 : $children->count();
    }

    /**
     * Gets number of descendants (children and their children)
     *
     * @return int
     */
    public function get_number_descendants()
    {
        return ($this->{$this->right_column} - $this->{$this->left_column} - 1) / 2;
    }

    /**
     * Inserts node as parent of dest record
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function insert_as_parent_of($node)
    {
        if ($this->is_valid_node())
        {
            throw new Database_Exception('Cannot insert existing node as parent');
        }

        // fix param
        $node = $this->get_node($node);

        if ($node->is_root())
        {
            throw new Database_Exception('Cannot insert as parent of root');
        }

        $new_left = $node->{$this->left_column};
        $new_right = $node->{$this->right_column} + 2;
        $new_scope = $node->{$this->scope_column};
        $new_level = $node->{$this->level_column};

        try
        {
            $this->_db->begin();

            // make space for new node
            $this->shift_rl_values($node->{$this->right_column} + 1, 2, $new_scope);

            // slide child nodes over one and down one to allow new parent to wrap them
            $this->and_scope(
                DB::update($this->table_name())
                    ->value($this->left_column, DB::expr("{$this->left_column} + 1"))
                    ->value($this->right_column, DB::expr("{$this->right_column} + 1"))
                    ->value($this->level_column, DB::expr("{$this->level_column} + 1"))
                    ->and_where($this->left_column, '>=', $new_left)
                    ->and_where($this->right_column, '<=', $new_right),
                $new_scope
            )->execute($this->_db);

            $this->{$this->level_column} = $new_level;
            $this->insert_node($new_left, $new_right, $new_scope);

            $this->_db->commit();
        }
        catch (Exception $e)
        {
            $this->_db->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * Inserts node as previous sibling of dest record
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function insert_as_prev_sibling_of($node)
    {
        if ($this->is_valid_node())
        {
            throw new Database_Exception('Cannot insert existing node as prev sibling of');
        }

        // fix param
        $node = $this->get_node($node);

        $new_left = $node->{$this->left_column};
        $new_right = $node->{$this->left_column} + 1;
        $new_scope = $node->{$this->scope_column};

        try
        {
            $this->_db->begin();

            $this->shift_rl_values($new_left, 2, $new_scope);
            $this->{$this->level_column} = $node->{$this->level_column};
            $this->insert_node($new_left, $new_right, $new_scope);

            $this->_db->commit();

            // upgrade node right, left values
            $node->{$this->left_column} += 2;
            $node->{$this->right_column} += 2;
        }
        catch (Exception $e)
        {
            $this->_db->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * Inserts node as next sibling of dest record
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function insert_as_next_sibling_of($node)
    {
        if ($this->is_valid_node())
        {
            throw new Database_Exception('Cannot insert existing node as next sibling of');
        }

        // fix param
        $node = $this->get_node($node);

        $new_left = $node->{$this->right_column} + 1;
        $new_right = $node->{$this->right_column} + 2;
        $new_scope = $node->{$this->scope_column};

        try
        {
            $this->_db->begin();

            $this->shift_rl_values($new_left, 2, $new_scope);
            $this->{$this->level_column} = $node->{$this->level_column};
            $this->insert_node($new_left, $new_right, $new_scope);

            $this->_db->commit();
        }
        catch (Exception $e)
        {
            $this->_db->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * Inserts node as first child of dest record
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function insert_as_first_child_of($node)
    {
        if ($this->is_valid_node())
        {
            throw new Database_Exception('Cannot insert existing node as first child of');
        }

        // fix param
        $node = $this->get_node($node);

        $new_left = $node->{$this->left_column} + 1;
        $new_right = $node->{$this->left_column} + 2;
        $new_scope = $node->{$this->scope_column};

        try
        {
            $this->_db->begin();

            $this->shift_rl_values($new_left, 2, $new_scope);
            $this->{$this->level_column} = $node->{$this->level_column} + 1;
            $this->insert_node($new_left, $new_right, $new_scope);

            $this->_db->commit();

            // upgrade node right value
            $node->{$this->right_column} += 2;
        }
        catch (Exception $e)
        {
            $this->_db->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * Insert node as last child of $node
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function insert_as_last_child_of($node)
    {
        if ($this->is_valid_node())
        {
            throw new Database_Exception('Cannot insert existing node as last child');
        }

        // fix param
        $node = $this->get_node($node);

        $new_left = $node->{$this->right_column};
        $new_right = $node->{$this->right_column} + 1;
        $new_scope = $node->{$this->scope_column};

        try
        {
            $this->_db->begin();

            $this->shift_rl_values($new_left, 2, $new_scope);
            $this->{$this->level_column} = $node->{$this->level_column} + 1;
            $this->insert_node($new_left, $new_right, $new_scope);

            $this->_db->commit();

            // upgrade node right value
            $node->{$this->right_column} += 2;

        }
        catch (Exception $e)
        {
            $this->_db->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * Accomplishes moving of nodes between different trees.
     * Used by the move* methods if the root values of the two nodes are different.
     *
     * @param Kohana_ORM_Nested_Sets $node
     * @param int                    $new_left_value
     * @param int                    $move_type
     *
     * @return bool
     */
    private function _move_between_trees($node, $new_left_value, $move_type)
    {
        try
        {
            $this->_db->begin();

            // move between trees: Detach from old tree & insert into new tree
            $new_scope = $node->{$this->scope_column};
            $old_scope = $this->{$this->scope_column};
            $old_lft = $this->{$this->left_column};
            $old_rgt = $this->{$this->right_column};
            $old_level = $this->{$this->level_column};

            // prepare target tree for insertion, make room
            $this->shift_rl_values($new_left_value, $old_rgt - $old_lft - 1, $new_scope);

            // set new root id for this node
            $this->{$this->scope_column} = $new_scope;
            parent::save();

            // insert this node as a new node
            $this->{$this->right_column} = 0;
            $this->{$this->left_column} = 0;

            switch ($move_type)
            {
                case 'move_as_prev_sibling_of':
                    $this->insert_as_prev_sibling_of($node);
                    break;

                case 'move_as_first_child_of':
                    $this->insert_as_first_child_of($node);
                    break;

                case 'move_as_next_sibling_of':
                    $this->insert_as_next_sibling_of($node);
                    break;

                case 'move_as_last_child_of':
                    $this->insert_as_last_child_of($node);
                    break;

                default:
                    throw new Database_Exception('Unknown move operation');
                    break;
            }

            $diff = $old_rgt - $old_lft;
            $this->{$this->right_column} = $this->{$this->left_column} + $old_rgt - $old_lft;
            parent::save();

            $new_level = $this->{$this->level_column};
            $level_diff = $new_level - $old_level;

            // relocate descendants of the node
            $diff = $this->{$this->left_column} - $old_lft;

            // update lft/rgt/root/level for all descendants
            $update = DB::update($this->table_name())
                ->value($this->left_column, DB::expr("{$this->left_column} + $diff"))
                ->value($this->right_column, DB::expr("{$this->right_column} + $diff"))
                ->value($this->level_column, DB::expr("{$this->level_column} + $level_diff"));
            if ($this->use_scope)
            {
                $update->value($this->scope_column, $new_scope);
            }
            $this->and_scope(
                $update->where($this->left_column, '>', $old_lft)
                    ->and_where($this->right_column, '<', $old_rgt),
                $old_scope
            )->execute($this->_db);

            // close gap in old tree
            $first = $old_rgt + 1;
            $delta = $old_lft - $old_rgt - 1;
            $this->shift_rl_values($first, $delta, $old_scope);

            $this->_db->commit();
        }
        catch (Exception $e)
        {
            $this->_db->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * Moves node as prev sibling of $node
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function move_as_prev_sibling_of($node)
    {
        // fix param
        $node = $this->get_node($node);

        if (!$this->loaded())
        {
            return $this->insert_as_prev_sibling_of($node);
        }

        if ($this->_primary_key_value === $node->_primary_key_value)
        {
            throw new Database_Exception('Cannot move node as previous sibling of itself');
        }

        if ($node->{$this->scope_column} != $this->{$this->scope_column})
        {
            // move between trees
            return $this->_move_between_trees($node, $node->{$this->left_column}, __FUNCTION__);
        }
        else
        {
            // move within the tree
            $old_level = $this->{$this->level_column};
            $this->{$this->level_column} = $node->{$this->level_column};
            $this->update_node($node->{$this->left_column}, $this->{$this->level_column} - $old_level);
        }

        return TRUE;
    }

    /**
     * Moves node as next sibling of dest record
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function move_as_next_sibling_of($node)
    {
        // fix param
        $node = $this->get_node($node);

        if (!$this->loaded())
        {
            return $this->insert_as_next_sibling_of($node);
        }

        if ($this->_primary_key_value === $node->_primary_key_value)
        {
            throw new Database_Exception('Cannot move node as next sibling of itself');
        }

        if ($node->{$this->scope_column} != $this->{$this->scope_column})
        {
            // move between trees
            return $this->_move_between_trees($node, $node->{$this->right_column} + 1, __FUNCTION__);
        }
        else
        {
            // move within tree
            $old_level = $this->{$this->level_column};
            $this->{$this->level_column} = $node->{$this->level_column};
            $this->update_node($node->{$this->right_column} + 1, $this->{$this->level_column} - $old_level);
        }

        return TRUE;
    }

    /**
     * Moves node as first child of dest record
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function move_as_first_child_of($node)
    {
        // fix param
        $node = $this->get_node($node);

        if (!$this->loaded())
        {
            return $this->insert_as_first_child_of($node);
        }

        if ($this->_primary_key_value === $node->_primary_key_value)
        {
            throw new Database_Exception('Cannot move node as first child of itself or into a descendant');
        }

        if ($node->{$this->scope_column} != $this->{$this->scope_column})
        {
            // move between trees
            return $this->_move_between_trees($node, $node->{$this->left_column} + 1, __FUNCTION__);
        }
        else
        {
            // move within tree
            $old_level = $this->{$this->level_column};
            $this->{$this->level_column} = $node->{$this->level_column} + 1;
            $this->update_node($node->{$this->left_column} + 1, $this->{$this->level_column} - $old_level);
        }

        return TRUE;
    }

    /**
     * Moves node as last child of dest record
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function move_as_last_child_of($node)
    {
        // fix param
        $node = $this->get_node($node);

        if (!$this->loaded())
        {
            return $this->insert_as_last_child_of($node);
        }

        if ($this->_primary_key_value === $node->_primary_key_value)
        {
            throw new Database_Exception('Cannot move node as last child of itself or into a descendant');
        }

        if ($node->{$this->scope_column} != $this->{$this->scope_column})
        {
            // move between trees
            return $this->_move_between_trees($node, $node->{$this->right_column}, __FUNCTION__);
        }
        else
        {
            // move within tree
            $old_level = $this->{$this->level_column};
            $this->{$this->level_column} = $node->{$this->level_column} + 1;
            $this->update_node($node->{$this->right_column}, $this->{$this->level_column} - $old_level);
        }

        return TRUE;
    }

    /**
     * Makes this node a root node. Only used in multiple-root trees.
     *
     * @param  int $new_scope New scope value
     *
     * @return bool
     */
    public function make_root($new_scope = NULL)
    {
        // TODO: throw exception instead?
        if ($this->is_root())
        {
            return TRUE;
        }

        // check scope
        if ($this->use_scope AND empty($new_scope))
        {
            $new_scope = $this->get_next_scope();
        }

        $old_rgt = intval($this->{$this->right_column});
        $old_lft = intval($this->{$this->left_column});
        $old_level = intval($this->{$this->level_column});
        $old_scope = intval($this->{$this->scope_column});

        try
        {
            $this->_db->begin();

            // update descendants lft/rgt/root/level values
            $diff = 1 - $old_lft;

            if ($this->loaded())
            {
                $update = DB::update($this->table_name())
                    ->value($this->left_column, DB::expr("{$this->left_column} + $diff"))
                    ->value($this->right_column, DB::expr("{$this->right_column} + $diff"))
                    ->value($this->level_column, DB::expr("{$this->level_column} + $old_level"));
                if ($this->use_scope)
                {
                    $update->value($this->scope_column, $new_scope)->where($this->scope_column, '=', $old_scope);
                }
                $update->and_where($this->left_column, '>', $old_lft)
                    ->and_where($this->right_column, '<', $old_rgt)
                    ->execute($this->_db);

                // detach from old tree (close gap in old tree)
                $first = $old_rgt + 1;
                $delta = $old_lft - $old_rgt - 1;
                $this->shift_rl_values($first, $delta, $old_scope);
            }

            // Set new lft/rgt/root/level values for root node
            $this->{$this->left_column} = 1;
            $this->{$this->right_column} = $this->loaded() ? $old_rgt - $old_lft + 1 : 2;
            $this->{$this->scope_column} = $new_scope;
            $this->{$this->level_column} = 0;

            parent::save();

            $this->_db->commit();
        }
        catch (Exception $e)
        {
            $this->_db->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * Inserts $node as last child for $this
     *
     * @param  Kohana_ORM_Nested_Sets $node Instance of Kohana_ORM_Nested_Sets
     *
     * @return bool
     */
    public function add_child(Kohana_ORM_Nested_Sets $node)
    {
        return $node->insert_as_last_child_of($this);
    }

    /**
     * Determines if node is leaf
     *
     * @return bool
     */
    public function is_leaf()
    {
        return (($this->{$this->right_column} - $this->{$this->left_column}) == 1);
    }

    /**
     * Determines if node is root
     *
     * @return bool
     */
    public function is_root()
    {
        return ($this->{$this->left_column} == 1);
    }

    /**
     * Determines if node is equal to subject node
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function is_equal_to($node)
    {
        // fix param
        $node = $this->get_node($node);

        return (($this->{$this->left_column} == $node->{$this->left_column}) AND
                ($this->{$this->right_column} == $node->{$this->right_column}) AND
                (!$this->use_scope OR ($this->{$this->scope_column} == $node->{$this->scope_column})));
    }

    /**
     * Determines if node is child of subject node
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function is_descendant_of($node)
    {
        // fix param
        $node = $this->get_node($node);

        return (($this->{$this->left_column} > $node->{$this->left_column}) AND
                ($this->{$this->right_column} < $node->{$this->right_column}) AND
                (!$this->use_scope OR ($this->{$this->scope_column} == $node->{$this->scope_column})));
    }

    /**
     * Determines if node is child of or sibling to subject node
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function is_descendant_of_or_equal_to($node)
    {
        // fix param
        $node = $this->get_node($node);

        return (($this->{$this->left_column} >= $node->{$this->left_column}) AND
                ($this->{$this->right_column} <= $node->{$this->right_column}) AND
                (!$this->use_scope OR ($this->{$this->scope_column} == $node->{$this->scope_column})));
    }

    /**
     * Determines if node is ancestor of subject node
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return bool
     */
    public function is_ancestor_of($node)
    {
        // fix param
        $node = $this->get_node($node);

        return (($node->{$this->left_column} > $this->{$this->left_column}) AND
                ($node->{$this->right_column} < $this->{$this->right_column}) AND
                (!$this->use_scope OR ($node->{$this->scope_column} == $this->{$this->scope_column})));
    }

    /**
     * Determines if node is valid
     *
     * @return bool
     */
    public function is_valid_node()
    {
        return intval($this->{$this->right_column}) > intval($this->{$this->left_column});
    }

    /**
     * Deletes node and it's descendants
     *
     * @return bool
     */
    public function delete()
    {
        try
        {
            $this->_db->begin();

            $this->and_scope(
                DB::delete($this->table_name())
                    ->where($this->left_column, '>=', $this->{$this->left_column})
                    ->and_where($this->right_column, '<=', $this->{$this->right_column})
            )->execute($this->_db);

            $first = $this->{$this->right_column} + 1;
            $delta = $this->{$this->left_column} - $this->{$this->right_column} - 1;
            $this->shift_rl_values($first, $delta, $this->{$this->scope_column});

            $this->_db->commit();
        }
        catch (Exception $e)
        {
            $this->_db->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * @see ORM::save
     *
     * @param null|Validation $validation
     *
     * @return bool|ORM
     */
    public function save(Validation $validation = NULL)
    {
        // overload basic ORM::save() method
        if (!$this->loaded())
        {
            return $this->make_root();
        }

        return parent::save();
    }

    /**
     * Check input param, and load node if needed
     *
     * @param  mixed $node Instance of Kohana_ORM_Nested_Sets or primary key value
     *
     * @return Kohana_ORM_Nested_Sets
     */
    private function get_node($node)
    {
        $old_node = $node;

        if (!($node instanceof self))
        {
            $node = self::factory(self::object_name(), $node);
        }

        if (!$node->loaded())
        {
            throw new Database_Exception("Cannot find node with :primary_key equal to :primary_key_value",
                array(':primary_key' => $this->_primary_key, ':primary_key_value' => $old_node));
        }

        return $node;
    }

    /**
     * Sets node's left, right, parent, scope values and save's it
     *
     * @param int $left   Node left value
     * @param int $right  Node right value
     * @param int $scope  Node scope value
     *
     * @return $this
     */
    private function insert_node($left = 0, $right = 0, $scope = 1)
    {
        $this->{$this->left_column} = $left;
        $this->{$this->right_column} = $right;
        $this->{$this->scope_column} = $scope;
        return parent::save();
    }

    /**
     * Move node's and its children to location $destLeft and updates rest of tree
     *
     * @param int $node_left Destination left value
     * @param int $level_diff
     *
     * @return bool
     */
    private function update_node($node_left, $level_diff)
    {
        $left = $this->{$this->left_column};
        $right = $this->{$this->right_column};
        $scope = $this->{$this->scope_column};

        $tree_size = $right - $left + 1;

        try
        {
            $this->_db->begin();

            // make room in the new branch
            $this->shift_rl_values($node_left, $tree_size, $scope);

            if ($left >= $node_left)
            {
                $left += $tree_size;
                $right += $tree_size;
            }

            // update level for descendants
            $this->and_scope(
                DB::update($this->table_name())
                    ->value($this->level_column, DB::expr("{$this->level_column} + $level_diff"))
                    ->where($this->left_column, '>', $left)
                    ->and_where($this->right_column, '<', $right)
            )->execute($this->_db);

            // now there's enough room next to target to move the subtree
            $this->shift_rl_range($left, $right, $node_left - $left, $scope);

            // correct values after source (close gap in old tree)
            $this->shift_rl_values($right + 1, -$tree_size, $scope);

            parent::save();
            parent::reload();

            $this->_db->commit();
        }
        catch (Exception $e)
        {
            $this->_db->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * Adds '$delta' to all Left and Right values that are >= '$first'. '$delta' can also be negative.
     *
     * Note: This method does wrap its database queries in a transaction. This should be done
     * by the invoking code.
     *
     * @param int   $first First node to be shifted
     * @param int   $delta Value to be shifted by, can be negative
     * @param mixed $scope Scope value
     *
     * @return $this
     */
    private function shift_rl_values($first, $delta, $scope)
    {
        $this->and_scope(
            DB::update($this->table_name())
                ->value($this->left_column, DB::expr("{$this->left_column} + $delta"))
                ->where($this->left_column, '>=', $first),
            $scope
        )->execute($this->_db);

        $this->and_scope(
            DB::update($this->table_name())
                ->value($this->right_column, DB::expr("{$this->right_column} + $delta"))
                ->where($this->right_column, '>=', $first),
            $scope
        )->execute($this->_db);

        return $this;
    }

    /**
     * Adds '$delta' to all Left and Right values that are >= '$first' and <= '$last'.
     * '$delta' can also be negative.
     *
     * Note: This method does wrap its database queries in a transaction. This should be done
     * by the invoking code.
     *
     * @param int   $first  First node to be shifted (L value)
     * @param int   $last   Last node to be shifted (L value)
     * @param int   $delta  Value to be shifted by, can be negative
     * @param mixed $scope  Scope value
     *
     * @return $this
     */
    private function shift_rl_range($first, $last, $delta, $scope)
    {
        $this->and_scope(
            DB::update($this->table_name())
                ->value($this->left_column, DB::expr("{$this->left_column} + $delta"))
                ->where($this->left_column, '>=', $first)
                ->and_where($this->left_column, '<=', $last),
            $scope
        )->execute($this->_db);


        $this->and_scope(
            DB::update($this->table_name())
                ->value($this->right_column, DB::expr("{$this->right_column} + $delta"))
                ->where($this->right_column, '>=', $first)
                ->and_where($this->right_column, '<=', $last),
            $scope
        )->execute($this->_db);

        return $this;
    }

    /**
     * Add scope condition in query if needed
     *
     * @param  mixed $object
     * @param  mixed $scope|null
     *
     * @return mixed
     */
    private function and_scope($object, $scope = NULL)
    {
        if ($this->use_scope)
        {
            $object->and_where($this->scope_column, '=', is_null($scope) ? $this->{$this->scope_column} : $scope);
        }

        return $object;
    }

    /**
     * Calculate next scope value
     *
     * @return int
     */
    private function get_next_scope()
    {
        // returns available value for scope
        $scope = DB::select(DB::expr("MAX({$this->scope_column}) as scope"))
                    ->from($this->_table_name)
                    ->execute($this->_db)
                    ->current();

        if (isset($scope['scope']) AND $scope['scope'] > 0)
        {
            return $scope['scope'] + 1;
        }

        return 1;
    }

    /**
     * Handles retrieval of all model values, relationships, and metadata.
     *
     * @param   string $column Column name
     * @return  mixed
     */
	public function __get($column)
    {
        if ($column == $this->scope_column AND !$this->use_scope)
        {
            return NULL;
        }

        return parent::__get($column);
    }

    /**
     * Base set method - this should not be overridden.
     *
     * @param  string $column  Column name
     * @param  mixed  $value   Column value
     * @return void
     */
	public function __set($column, $value)
    {
        if ($column == $this->scope_column AND !$this->use_scope)
        {
            return;
        }

        parent::__set($column, $value);
    }

    /**
     * Handles setting of column
     *
     * @param  string $column Column name
     * @param  mixed  $value  Column value
     * @return void
     */
    public function set($column, $value)
    {
        if ($column == $this->scope_column AND !$this->use_scope)
        {
            return;
        }

        parent::set($column, $value);
    }
}