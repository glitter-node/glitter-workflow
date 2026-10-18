<?php
namespace Modules\Glitter\Workflow\Enums;
enum AcceptanceStatus:string { case Pending='pending'; case Processing='processing'; case Processed='processed'; case Failed='failed'; }
