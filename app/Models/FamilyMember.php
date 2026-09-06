<?php

namespace App\Models;

class FamilyMember extends Person
{
    public function getMorphClass()
    {
        return 'person';
    }
}
