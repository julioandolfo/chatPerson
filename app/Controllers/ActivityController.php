<?php
/**
 * Controller de Atividades
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Models\Activity;

class ActivityController
{
    /**
     * Listar atividades
     */
    public function index(): void
    {
        Permission::abortIfCannot('activities.view');
        
        $filters = [
            'user_id' => Request::get('user_id'),
            'activity_type' => Request::get('activity_type'),
            'entity_type' => Request::get('entity_type'),
            'entity_id' => Request::get('entity_id'),
            'date_from' => Request::get('date_from'),
            'date_to' => Request::get('date_to'),
            'search' => Request::get('search'),
            'limit' => Request::get('limit', 50),
            'offset' => Request::get('offset', 0)
        ];
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $activities = Activity::getAll($filters);
            $activityTypes = Activity::getActivityTypes();
            
            Response::view('activities/index', [
                'activities' => $activities,
                'activityTypes' => $activityTypes,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('activities/index', [
                'activities' => [],
                'activityTypes' => Activity::getActivityTypes(),
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obter atividades por usuário (JSON)
     */
    public function getByUser(int $userId): void
    {
        Permission::abortIfCannot('activities.view');
        
        try {
            $filters = [
                'limit' => Request::get('limit', 50),
                'offset' => Request::get('offset', 0),
                'activity_type' => Request::get('activity_type'),
                'date_from' => Request::get('date_from'),
                'date_to' => Request::get('date_to')
            ];
            
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });
            
            $activities = Activity::getByUser($userId, $filters);
            $stats = Activity::getStatsByUser($userId, $filters['date_from'] ?? null, $filters['date_to'] ?? null);
            
            Response::json([
                'success' => true,
                'activities' => $activities,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter atividades por entidade (JSON)
     */
    public function getByEntity(string $entityType, int $entityId): void
    {
        Permission::abortIfCannot('activities.view');
        
        try {
            $filters = [
                'limit' => Request::get('limit', 50),
                'offset' => Request::get('offset', 0),
                'activity_type' => Request::get('activity_type'),
                'date_from' => Request::get('date_from'),
                'date_to' => Request::get('date_to')
            ];
            
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });
            
            $activities = Activity::getByEntity($entityType, $entityId, $filters);
            
            Response::json([
                'success' => true,
                'activities' => $activities
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

