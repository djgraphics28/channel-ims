<?php

namespace App\Livewire\Widget;

use Livewire\Component;
use Illuminate\Database\Eloquent\Model;

class ActiveStatusChange extends Component
{
    public Model $model;

    public $field;

    public $is_active;

    public function mount()
    {
        $this->is_active = (bool) $this->model->getAttribute($this->field);
    }

    public function updating($field, $value)
    {
        $this->model->setAttribute($this->field, $value)->save();

        if($value) {
            flash()->success('This user has been activated');
        } else {
            flash()->warning('This user has been deactivated');
        }

    }

    public function render()
    {
        return view('livewire.widget.active-status-change');
    }
}
