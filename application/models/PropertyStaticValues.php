<?php

namespace app\models;

use Yii;
use yii\behaviors\AttributeBehavior;
use yii\caching\TagDependency;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "property_static_values".
 *
 * @property integer $id
 * @property integer $property_id
 * @property string $name
 * @property string $value
 * @property string $slug
 * @property integer $sort_order
 * @property Property $property
 */
class PropertyStaticValues extends ActiveRecord
{
    public static $identity_map_by_property_id = [];
    private static $identity_map = [];

    public function behaviors()
    {
        return [
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => 'sort_order',
                ],
                'value' => 0,
            ],
            [
                'class' => \devgroup\TagDependencyHelper\ActiveRecordHelper::className(),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%property_static_values}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['property_id', 'name', 'value'], 'required'],
            [['property_id', 'sort_order', 'dont_filter'], 'integer'],
            [['name', 'value', 'slug', 'title_append'], 'string']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'property_id' => Yii::t('app', 'Property ID'),
            'name' => Yii::t('app', 'Name'),
            'value' => Yii::t('app', 'Value'),
            'slug' => Yii::t('app', 'Slug'),
            'sort_order' => Yii::t('app', 'Sort Order'),
        ];
    }

    /**
     * Search tasks
     * @param $params
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = static::find()
            ->where(['property_id'=>$this->property_id]);
        $dataProvider = new ActiveDataProvider(
            [
                'query' => $query,
                'pagination' => [
                    'pageSize' => 10,
                ],
            ]
        );
        if (!($this->load($params))) {
            return $dataProvider;
        }
        $query->andFilterWhere(['id' => $this->id]);
        $query->andFilterWhere(['like', 'name', $this->name]);
        $query->andFilterWhere(['like', 'value', $this->value]);
        $query->andFilterWhere(['like', 'slug', $this->slug]);
        return $dataProvider;
    }

    public function getProperty()
    {
        return $this->hasOne(Property::className(), ['id' => 'property_id']);
    }

    /**
     * Возвращает Массив! по ID с использованием IdentityMap
     *
     * @param int $id
     * @return null|PropertyStaticValues
     */
    public static function findById($id)
    {
        if (!isset(static::$identity_map[$id])) {
            $cacheKey = "PropertyStaticValue:$id";

            if (false === $property = Yii::$app->cache->get($cacheKey)) {
                if (null !== $property = static::find()->where(['id' => $id])->asArray()->one()) {
                    Yii::$app->cache->set(
                        $cacheKey,
                        $property,
                        0,
                        new TagDependency(
                            [
                                'tags' => [
                                    \devgroup\TagDependencyHelper\ActiveRecordHelper::getObjectTag(static::className(), $id),
                                ],
                            ]
                        )
                    );
                }
            }
            static::$identity_map[$id] = $property;
        }
        return static::$identity_map[$id];
    }

    /**
     * Возвращает массив возможных значений свойств по property_id
     * Внимание! Это массивы, а не объекты!
     * Это сделано для экономии памяти.
     * Используется identity_map
     */
    public static function getValuesForPropertyId($property_id)
    {
        if (!isset(static::$identity_map_by_property_id[$property_id])) {
            static::$identity_map_by_property_id[$property_id] =
                static::arrayOfValuesForPropertyId($property_id);
            foreach (static::$identity_map_by_property_id[$property_id] as $psv) {
                static::$identity_map[$psv['id']] = $psv;
            }
        }
        return static::$identity_map_by_property_id[$property_id];
    }

    public static function getSelectForPropertyId($property_id)
    {
        $values = PropertyStaticValues::getValuesForPropertyId($property_id);
        $result = [];
        foreach ($values as $row) {
            $result[$row['id']] = $row['name'];
        }
        return $result;
    }

    /**
     * Аналогично getValuesForPropertyId
     * Но identity_map не используется
     *
     * @param int $property_id
     * @return array|mixed|\yii\db\ActiveRecord[]
     */
    public static function arrayOfValuesForPropertyId($property_id)
    {
        $cacheKey = "ValuesForProperty:$property_id";

        if (false === $values = Yii::$app->cache->get($cacheKey)) {
            $values = static::find()
                ->where(['property_id'=>$property_id])
                ->orderBy([
                    'sort_order' => SORT_ASC,
                    'name' => SORT_ASC
                ])
                ->asArray()
                ->all();
            if (null !== $values) {
                Yii::$app->cache->set(
                    $cacheKey,
                    $values,
                    0,
                    new TagDependency([
                        'tags' => [
                            \devgroup\TagDependencyHelper\ActiveRecordHelper::getObjectTag(Property::className(), $property_id)
                        ]
                    ])
                );
            }
        }
        return $values;
    }

    public function afterSave($insert, $changedAttributes)
    {
        if (null !== $parent = Property::findById($this->property_id)) {
            $parent->invalidateModelCache();
        }
        parent::afterSave($insert, $changedAttributes);
    }

    public function beforeDelete()
    {
        if (null !== $parent = Property::findById($this->property_id)) {
            $parent->invalidateModelCache();
        }
        return parent::beforeDelete();
    }
}
