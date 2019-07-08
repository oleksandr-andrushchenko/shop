<?php

namespace SNOWGIRL_SHOP\Import;

use SNOWGIRL_SHOP\Import;
use SNOWGIRL_CORE\Helper\Arrays;

class Gepur extends Import
{
    protected $sources = [
        'size_id' => 'product_sizes',
        'tag_id' => 'product_description',
        'color_id' => 'product_color',
        'material_id' => 'product_description'
    ];
    protected $langs = ['ru'];

    protected $csvProcessorV2 = true;
    protected $mvaProcessorV2 = true;

    protected $csvFileDelimiter = ';';
    protected $csvFileEncoding = 'UTF8';

    protected $categories;

    protected $decreasePriceRate = 0;

    protected function initialize()
    {
        $this->categories = Arrays::mapByKeyValueMaker(explode("\n", '2	Верхняя одежда
3	Платья
4	Костюмы и комплекты
5	Свитера
6	Комбинезоны
7	Брюки, леггинсы, шорты
8	Юбки
9	Спортивные костюмы
10	Купальники, пляжные туники
11	Сумки, клатчи, кошельки
12	Одежда для дома
13	Перчатки, шарфы, шапки
14	Обувь
15	Аксессуары
16	Детская одежда
17	Мужская одежда
18	Пальто
19	Куртки
20	Жилетки
21	Шубы
22	Плащи и кардиганы
23	Пиджаки
24	Дутые костюмы и комбинезоны
25	Большие размеры
26	Вечерние
27	Сарафаны
28	Мини
29	Миди
30	Макси
31	Теплые
32	Туники
33	Спортивные
34	Большие размеры
35	Для будущих мам
36	Верх пиджак
37	Верх топ
38	Верх жилет
39	Низ брюки
40	Низ леггинсы
41	Низ юбка
42	Низ шорты
43	Вязанные
44	Большие размеры
45	Теплые
46	Кофты
47	Рубашки
48	Блузы
49	Футболки
50	Майки
51	Большие размеры
52	Брючные
53	С шортами
54	Велюровые
55	Большие размеры
56	Брюки
57	Спортивные
58	Джинсы
59	Леггинсы и лосины
60	Шорты
61	Большие размеры
62	В пол
63	Карандаш
64	Полусолнце
65	Кожаные
66	Большие размеры
67	Теплые
68	Велюровые
69	Эластичные
70	Трикотажные
71	С капюшоном
72	Со штанами
73	С леггинсами
74	С шортиками
75	Большие размеры
76	Купальники
77	Пляжные туники
78	Большие размеры
79	Сумки
80	Рюкзаки
81	Клатчи
82	Кошельки
83	Перчатки
84	Шарфы
85	Платки
86	Шапки
87	Шляпки
88	Комплекты
89	Туфли
90	Сапоги
91	Сникерсы
92	Ботинки
93	Лоферы
94	Угги
95	Бижутерия
96	Часы
97	Очки
98	Брелоки
99	Ремни
100	Кашемировое
101	На синтепоне
102	Неопрен
103	Удлиненные
104	Утепленные
105	Костюмные
106	Кожаные куртки
107	Ветровки
108	Парки
109	На синтепоне
110	Вязанные свитера
111	Вязанные туники
112	Вязанные кардиганы
113	Худи и батники
117	С принтом
120	Спортивные
121	Высокая талия
122	Кожаные
124	Пляжные
127	Браслеты
128	Колье
129	Серьги
130	Кольца
131	Комплекты
132	Металлические
133	Керамические
134	Каучуковые
135	Кожаный ремешок
136	Текстильный ремешек
137	Пластиковая оправа
138	Металлическая оправа
139	Новинки
143	Звёздные коллекции
144	Айза Долматова
145	Алёна Шишкова
146	Ксения Бородина
147	Ольга Бузова
149	ТОП продаж
150	Анастасия Решетова
151	Классические
152	Классические
153	Боди
154	Классические
155	Светлана Пермякова
156	Одежда для беременных
157	Платья и сарафаны
158	Блузы джемпера туники
159	Брюки леггинсы
160	Спортивная одежда
161	Верхняя одежда (Беременные)
162	Букле
163	Нижнее бельё, пижама
164	Анна Хилькевич
165	Я Любовь
167	Носки, колготки
168	Кепки
169	Повязки на голову
170	Сандалии
171	Босоножки
172	Слипоны
173	Косметички
174	На выпускной
175	Нижнее белье 
176	Пижама
177	Анна Тринчер
178	Лиза Василенко
179	Футболки, майки
180	Топы
181	Большие размеры
182	Блузы, рубашки
183	Большие размеры
'), function ($i, $line) {
            false && $i;
            $line = preg_split('/\s/', $line);
            return [trim(array_shift($line)), trim(implode(' ', $line))];
        });
    }

    protected function preNormalizeRow($row)
    {
        if (isset($this->indexes['id_category']) && isset($this->columns[$this->indexes['id_category']])) {
            $row[$this->indexes['id_category']] = $this->categories[$row[$this->indexes['id_category']]];
        }

        return $row;
    }

    protected function postNormalizeRow($row)
    {
        if (isset($this->mappings['image'])) {
            $index = $this->indexes[$this->mappings['image']['column']];
            $row[$index] = trim($row[$index], ',');
//            $row[$index] = explode(',', $row[$index])[0];
        }

        if (isset($this->mappings['price'])) {
            $index = $this->indexes[$this->mappings['price']['column']];
            $row[$index] = preg_replace('/^.*RUB:([0-9]+).*$/', '$1', $row[$index]);
        }

        if (isset($this->mappings['description'])) {
            $index = $this->indexes[$this->mappings['description']['column']];
            $row[$index] = strip_tags($row[$index]);
        }

        return $row;
    }

    /**
     * Returns normalized names
     *
     * @param $row
     *
     * @return array
     */
    protected function importRowToSizes($row)
    {
        if (isset($this->sources['size_id']) && isset($this->indexes[$this->sources['size_id']]) && $source = trim($row[$this->indexes[$this->sources['size_id']]])) {
            return array_map(function ($tmp) {
                return strtoupper(trim($tmp));
            }, explode(',', $source));
        }

        return [];
    }

    /**
     * @todo test..
     *
     * @param $row
     *
     * @return array
     */
    protected function importRowToMaterials($row)
    {
        if (isset($this->sources['size_id']) && isset($this->indexes[$this->sources['size_id']]) && $source = trim($row[$this->indexes[$this->sources['size_id']]])) {
            if (preg_match('/Материал: ([a-zA-ZА-Яа-яЁё\s,-]+)/ui', $source, $tmp)) {
                $output = [];

                foreach ($tmp[2] as $v) {
                    $output = array_merge($output, explode(',', $v));
                }

                return $output;
            }

            return parent::importRowToMaterials($row);
        }

        return [];
    }
}