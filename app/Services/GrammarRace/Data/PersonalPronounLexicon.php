<?php

namespace App\Services\GrammarRace\Data;

final class PersonalPronounLexicon
{
    /** @return list<string> */
    public static function maleNames(): array
    {
        return [
            'Adam', 'Adrian', 'Alan', 'Andrew', 'Anthony', 'Arthur', 'Ben', 'Benjamin', 'Blake', 'Bob',
            'Brandon', 'Brian',
            'Caleb', 'Charles', 'Christopher', 'Daniel', 'David', 'Dennis', 'Edward', 'Eric', 'Ethan', 'Felix',
            'Frank', 'George', 'Harry', 'Henry', 'Ian', 'Jack', 'Jacob', 'James', 'Jason', 'John',
            'Joseph', 'Kevin', 'Leo', 'Liam', 'Lucas', 'Mark', 'Martin', 'Matthew', 'Michael', 'Nathan',
            'Mike', 'Nicholas', 'Noah', 'Oliver', 'Oscar', 'Paul', 'Peter', 'Philip', 'Richard', 'Robert',
            'Thomas', 'Tom', 'Victor', 'William',
        ];
    }

    /** @return list<string> */
    public static function femaleNames(): array
    {
        return [
            'Abigail', 'Alice', 'Amelia', 'Anna', 'Audrey', 'Ava', 'Bella', 'Chloe', 'Claire', 'Daisy',
            'Diana', 'Eleanor', 'Elizabeth', 'Ella', 'Emily', 'Emma', 'Eva', 'Evelyn', 'Fiona', 'Grace',
            'Hannah', 'Helen', 'Isabella', 'Jane', 'Jessica', 'Julia', 'Kate', 'Laura', 'Lily', 'Linda',
            'Lucy', 'Maria', 'Mary', 'Maya', 'Mia', 'Molly', 'Natalie', 'Nicole', 'Olivia', 'Penelope',
            'Rachel', 'Rebecca', 'Rose', 'Ruby', 'Sarah', 'Sofia', 'Sophie', 'Susan', 'Victoria', 'Zoe',
        ];
    }

    /**
     * English surnames are not gendered: the same curated list is used with
     * both Mr and Ms.
     *
     * @return list<string>
     */
    public static function surnames(): array
    {
        return [
            'Adams', 'Allen', 'Anderson', 'Baker', 'Bell', 'Brown', 'Campbell', 'Carter', 'Clark', 'Collins',
            'Cook', 'Cooper', 'Davis', 'Edwards', 'Evans', 'Foster', 'Green', 'Hall', 'Harris', 'Hill',
            'Hughes', 'Jackson', 'Johnson', 'Jones', 'Kelly', 'King', 'Lee', 'Lewis', 'Martin', 'Miller',
            'Mitchell', 'Moore', 'Morgan', 'Morris', 'Murphy', 'Nelson', 'Parker', 'Phillips', 'Reed', 'Roberts',
            'Robinson', 'Scott', 'Smith', 'Taylor', 'Thomas', 'Thompson', 'Walker', 'White', 'Williams', 'Wilson',
            'Wood', 'Wright', 'Young',
        ];
    }

    /** @return array<string, string> */
    public static function beginnerNameTranslations(): array
    {
        return [
            'Anna' => 'Анна', 'Alice' => 'Элис', 'Emma' => 'Эмма', 'Helen' => 'Хелен', 'Jane' => 'Джейн',
            'Kate' => 'Кейт', 'Lily' => 'Лили', 'Lucy' => 'Люси', 'Mary' => 'Мэри', 'Sofia' => 'София',
            'Adam' => 'Адам', 'Ben' => 'Бен', 'Bob' => 'Боб', 'David' => 'Дэвид', 'Harry' => 'Гарри',
            'Jack' => 'Джек', 'John' => 'Джон', 'Mike' => 'Майк', 'Peter' => 'Питер', 'Tom' => 'Том',
        ];
    }

    /** @return list<array{en: string, ru: string}> */
    public static function maleRelations(): array
    {
        return [
            ['en' => 'My brother', 'ru' => 'Мой брат'],
            ['en' => 'Her father', 'ru' => 'Её папа'],
            ['en' => 'His grandfather', 'ru' => 'Его дедушка'],
            ['en' => 'My uncle', 'ru' => 'Мой дядя'],
            ['en' => 'The boy', 'ru' => 'Мальчик'],
            ['en' => 'The man', 'ru' => 'Мужчина'],
        ];
    }

    /** @return list<array{en: string, ru: string}> */
    public static function femaleRelations(): array
    {
        return [
            ['en' => 'My sister', 'ru' => 'Моя сестра'],
            ['en' => 'Her mother', 'ru' => 'Её мама'],
            ['en' => 'His grandmother', 'ru' => 'Его бабушка'],
            ['en' => 'My aunt', 'ru' => 'Моя тётя'],
            ['en' => 'The girl', 'ru' => 'Девочка'],
            ['en' => 'The woman', 'ru' => 'Женщина'],
        ];
    }

    /** @return list<array{en: string, ru: string}> */
    public static function singularNature(): array
    {
        return [
            ['en' => 'The sky', 'ru' => 'Небо'],
            ['en' => 'The Moon', 'ru' => 'Луна'],
            ['en' => 'The Sun', 'ru' => 'Солнце'],
        ];
    }

    /**
     * All English plurals are stored explicitly and use regular spelling
     * rules. Irregular nouns such as child/children and mouse/mice are
     * intentionally absent.
     *
     * @return list<array{en: string, plural: string, ru: string, ruPlural: string, gender: 'm'|'f'|'n'}>
     */
    public static function objects(): array
    {
        return [
            ['en' => 'book', 'plural' => 'books', 'ru' => 'книга', 'ruPlural' => 'книги', 'gender' => 'f'],
            ['en' => 'pen', 'plural' => 'pens', 'ru' => 'ручка', 'ruPlural' => 'ручки', 'gender' => 'f'],
            ['en' => 'pencil', 'plural' => 'pencils', 'ru' => 'карандаш', 'ruPlural' => 'карандаши', 'gender' => 'm'],
            ['en' => 'ruler', 'plural' => 'rulers', 'ru' => 'линейка', 'ruPlural' => 'линейки', 'gender' => 'f'],
            ['en' => 'desk', 'plural' => 'desks', 'ru' => 'парта', 'ruPlural' => 'парты', 'gender' => 'f'],
            ['en' => 'chair', 'plural' => 'chairs', 'ru' => 'стул', 'ruPlural' => 'стулья', 'gender' => 'm'],
            ['en' => 'table', 'plural' => 'tables', 'ru' => 'стол', 'ruPlural' => 'столы', 'gender' => 'm'],
            ['en' => 'bag', 'plural' => 'bags', 'ru' => 'сумка', 'ruPlural' => 'сумки', 'gender' => 'f'],
            ['en' => 'notebook', 'plural' => 'notebooks', 'ru' => 'тетрадь', 'ruPlural' => 'тетради', 'gender' => 'f'],
            ['en' => 'marker', 'plural' => 'markers', 'ru' => 'маркер', 'ruPlural' => 'маркеры', 'gender' => 'm'],
            ['en' => 'eraser', 'plural' => 'erasers', 'ru' => 'ластик', 'ruPlural' => 'ластики', 'gender' => 'm'],
            ['en' => 'map', 'plural' => 'maps', 'ru' => 'карта', 'ruPlural' => 'карты', 'gender' => 'f'],
            ['en' => 'picture', 'plural' => 'pictures', 'ru' => 'картина', 'ruPlural' => 'картины', 'gender' => 'f'],
            ['en' => 'poster', 'plural' => 'posters', 'ru' => 'плакат', 'ruPlural' => 'плакаты', 'gender' => 'm'],
            ['en' => 'box', 'plural' => 'boxes', 'ru' => 'коробка', 'ruPlural' => 'коробки', 'gender' => 'f'],
            ['en' => 'brush', 'plural' => 'brushes', 'ru' => 'щётка', 'ruPlural' => 'щётки', 'gender' => 'f'],
            ['en' => 'clock', 'plural' => 'clocks', 'ru' => 'будильник', 'ruPlural' => 'будильники', 'gender' => 'm'],
            ['en' => 'board', 'plural' => 'boards', 'ru' => 'доска', 'ruPlural' => 'доски', 'gender' => 'f'],
            ['en' => 'card', 'plural' => 'cards', 'ru' => 'карточка', 'ruPlural' => 'карточки', 'gender' => 'f'],
            ['en' => 'page', 'plural' => 'pages', 'ru' => 'страница', 'ruPlural' => 'страницы', 'gender' => 'f'],
            ['en' => 'cup', 'plural' => 'cups', 'ru' => 'чашка', 'ruPlural' => 'чашки', 'gender' => 'f'],
            ['en' => 'plate', 'plural' => 'plates', 'ru' => 'тарелка', 'ruPlural' => 'тарелки', 'gender' => 'f'],
            ['en' => 'spoon', 'plural' => 'spoons', 'ru' => 'ложка', 'ruPlural' => 'ложки', 'gender' => 'f'],
            ['en' => 'fork', 'plural' => 'forks', 'ru' => 'вилка', 'ruPlural' => 'вилки', 'gender' => 'f'],
            ['en' => 'bottle', 'plural' => 'bottles', 'ru' => 'бутылка', 'ruPlural' => 'бутылки', 'gender' => 'f'],
            ['en' => 'glass', 'plural' => 'glasses', 'ru' => 'стакан', 'ruPlural' => 'стаканы', 'gender' => 'm'],
            ['en' => 'lamp', 'plural' => 'lamps', 'ru' => 'лампа', 'ruPlural' => 'лампы', 'gender' => 'f'],
            ['en' => 'sofa', 'plural' => 'sofas', 'ru' => 'диван', 'ruPlural' => 'диваны', 'gender' => 'm'],
            ['en' => 'bed', 'plural' => 'beds', 'ru' => 'кровать', 'ruPlural' => 'кровати', 'gender' => 'f'],
            ['en' => 'door', 'plural' => 'doors', 'ru' => 'дверь', 'ruPlural' => 'двери', 'gender' => 'f'],
            ['en' => 'window', 'plural' => 'windows', 'ru' => 'окно', 'ruPlural' => 'окна', 'gender' => 'n'],
            ['en' => 'room', 'plural' => 'rooms', 'ru' => 'комната', 'ruPlural' => 'комнаты', 'gender' => 'f'],
            ['en' => 'carpet', 'plural' => 'carpets', 'ru' => 'ковёр', 'ruPlural' => 'ковры', 'gender' => 'm'],
            ['en' => 'mirror', 'plural' => 'mirrors', 'ru' => 'зеркало', 'ruPlural' => 'зеркала', 'gender' => 'n'],
            ['en' => 'basket', 'plural' => 'baskets', 'ru' => 'корзина', 'ruPlural' => 'корзины', 'gender' => 'f'],
            ['en' => 'towel', 'plural' => 'towels', 'ru' => 'полотенце', 'ruPlural' => 'полотенца', 'gender' => 'n'],
            ['en' => 'pillow', 'plural' => 'pillows', 'ru' => 'подушка', 'ruPlural' => 'подушки', 'gender' => 'f'],
            ['en' => 'ball', 'plural' => 'balls', 'ru' => 'мяч', 'ruPlural' => 'мячи', 'gender' => 'm'],
            ['en' => 'doll', 'plural' => 'dolls', 'ru' => 'кукла', 'ruPlural' => 'куклы', 'gender' => 'f'],
            ['en' => 'robot', 'plural' => 'robots', 'ru' => 'робот', 'ruPlural' => 'роботы', 'gender' => 'm'],
            ['en' => 'kite', 'plural' => 'kites', 'ru' => 'воздушный змей', 'ruPlural' => 'воздушные змеи', 'gender' => 'm'],
            ['en' => 'puzzle', 'plural' => 'puzzles', 'ru' => 'пазл', 'ruPlural' => 'пазлы', 'gender' => 'm'],
            ['en' => 'block', 'plural' => 'blocks', 'ru' => 'кубик', 'ruPlural' => 'кубики', 'gender' => 'm'],
            ['en' => 'drum', 'plural' => 'drums', 'ru' => 'барабан', 'ruPlural' => 'барабаны', 'gender' => 'm'],
            ['en' => 'plane', 'plural' => 'planes', 'ru' => 'самолёт', 'ruPlural' => 'самолёты', 'gender' => 'm'],
            ['en' => 'train', 'plural' => 'trains', 'ru' => 'поезд', 'ruPlural' => 'поезда', 'gender' => 'm'],
            ['en' => 'boat', 'plural' => 'boats', 'ru' => 'лодка', 'ruPlural' => 'лодки', 'gender' => 'f'],
            ['en' => 'car', 'plural' => 'cars', 'ru' => 'машина', 'ruPlural' => 'машины', 'gender' => 'f'],
            ['en' => 'bike', 'plural' => 'bikes', 'ru' => 'велосипед', 'ruPlural' => 'велосипеды', 'gender' => 'm'],
            ['en' => 'scooter', 'plural' => 'scooters', 'ru' => 'самокат', 'ruPlural' => 'самокаты', 'gender' => 'm'],
            ['en' => 'game', 'plural' => 'games', 'ru' => 'игра', 'ruPlural' => 'игры', 'gender' => 'f'],
            ['en' => 'coin', 'plural' => 'coins', 'ru' => 'монета', 'ruPlural' => 'монеты', 'gender' => 'f'],
            ['en' => 'ring', 'plural' => 'rings', 'ru' => 'кольцо', 'ruPlural' => 'кольца', 'gender' => 'n'],
            ['en' => 'star', 'plural' => 'stars', 'ru' => 'звезда', 'ruPlural' => 'звёзды', 'gender' => 'f'],
            ['en' => 'apple', 'plural' => 'apples', 'ru' => 'яблоко', 'ruPlural' => 'яблоки', 'gender' => 'n'],
            ['en' => 'banana', 'plural' => 'bananas', 'ru' => 'банан', 'ruPlural' => 'бананы', 'gender' => 'm'],
            ['en' => 'orange', 'plural' => 'oranges', 'ru' => 'апельсин', 'ruPlural' => 'апельсины', 'gender' => 'm'],
            ['en' => 'lemon', 'plural' => 'lemons', 'ru' => 'лимон', 'ruPlural' => 'лимоны', 'gender' => 'm'],
            ['en' => 'cake', 'plural' => 'cakes', 'ru' => 'торт', 'ruPlural' => 'торты', 'gender' => 'm'],
            ['en' => 'cookie', 'plural' => 'cookies', 'ru' => 'печенье', 'ruPlural' => 'печенья', 'gender' => 'n'],
            ['en' => 'sandwich', 'plural' => 'sandwiches', 'ru' => 'бутерброд', 'ruPlural' => 'бутерброды', 'gender' => 'm'],
            ['en' => 'carrot', 'plural' => 'carrots', 'ru' => 'морковь', 'ruPlural' => 'морковки', 'gender' => 'f'],
            ['en' => 'onion', 'plural' => 'onions', 'ru' => 'луковица', 'ruPlural' => 'луковицы', 'gender' => 'f'],
            ['en' => 'grape', 'plural' => 'grapes', 'ru' => 'виноградина', 'ruPlural' => 'виноградины', 'gender' => 'f'],
            ['en' => 'pear', 'plural' => 'pears', 'ru' => 'груша', 'ruPlural' => 'груши', 'gender' => 'f'],
            ['en' => 'peach', 'plural' => 'peaches', 'ru' => 'персик', 'ruPlural' => 'персики', 'gender' => 'm'],
            ['en' => 'plum', 'plural' => 'plums', 'ru' => 'слива', 'ruPlural' => 'сливы', 'gender' => 'f'],
            ['en' => 'melon', 'plural' => 'melons', 'ru' => 'дыня', 'ruPlural' => 'дыни', 'gender' => 'f'],
            ['en' => 'egg', 'plural' => 'eggs', 'ru' => 'яйцо', 'ruPlural' => 'яйца', 'gender' => 'n'],
            ['en' => 'cracker', 'plural' => 'crackers', 'ru' => 'крекер', 'ruPlural' => 'крекеры', 'gender' => 'm'],
            ['en' => 'pizza', 'plural' => 'pizzas', 'ru' => 'пицца', 'ruPlural' => 'пиццы', 'gender' => 'f'],
            ['en' => 'tree', 'plural' => 'trees', 'ru' => 'дерево', 'ruPlural' => 'деревья', 'gender' => 'n'],
            ['en' => 'flower', 'plural' => 'flowers', 'ru' => 'цветок', 'ruPlural' => 'цветы', 'gender' => 'm'],
            ['en' => 'cloud', 'plural' => 'clouds', 'ru' => 'облако', 'ruPlural' => 'облака', 'gender' => 'n'],
            ['en' => 'river', 'plural' => 'rivers', 'ru' => 'река', 'ruPlural' => 'реки', 'gender' => 'f'],
            ['en' => 'lake', 'plural' => 'lakes', 'ru' => 'озеро', 'ruPlural' => 'озёра', 'gender' => 'n'],
            ['en' => 'mountain', 'plural' => 'mountains', 'ru' => 'гора', 'ruPlural' => 'горы', 'gender' => 'f'],
            ['en' => 'garden', 'plural' => 'gardens', 'ru' => 'сад', 'ruPlural' => 'сады', 'gender' => 'm'],
            ['en' => 'park', 'plural' => 'parks', 'ru' => 'парк', 'ruPlural' => 'парки', 'gender' => 'm'],
            ['en' => 'stone', 'plural' => 'stones', 'ru' => 'камень', 'ruPlural' => 'камни', 'gender' => 'm'],
            ['en' => 'shell', 'plural' => 'shells', 'ru' => 'ракушка', 'ruPlural' => 'ракушки', 'gender' => 'f'],
            ['en' => 'plant', 'plural' => 'plants', 'ru' => 'растение', 'ruPlural' => 'растения', 'gender' => 'n'],
            ['en' => 'forest', 'plural' => 'forests', 'ru' => 'лес', 'ruPlural' => 'леса', 'gender' => 'm'],
            ['en' => 'island', 'plural' => 'islands', 'ru' => 'остров', 'ruPlural' => 'острова', 'gender' => 'm'],
            ['en' => 'field', 'plural' => 'fields', 'ru' => 'поле', 'ruPlural' => 'поля', 'gender' => 'n'],
            ['en' => 'pond', 'plural' => 'ponds', 'ru' => 'пруд', 'ruPlural' => 'пруды', 'gender' => 'm'],
            ['en' => 'wave', 'plural' => 'waves', 'ru' => 'волна', 'ruPlural' => 'волны', 'gender' => 'f'],
            ['en' => 'shirt', 'plural' => 'shirts', 'ru' => 'рубашка', 'ruPlural' => 'рубашки', 'gender' => 'f'],
            ['en' => 'skirt', 'plural' => 'skirts', 'ru' => 'юбка', 'ruPlural' => 'юбки', 'gender' => 'f'],
            ['en' => 'dress', 'plural' => 'dresses', 'ru' => 'платье', 'ruPlural' => 'платья', 'gender' => 'n'],
            ['en' => 'jacket', 'plural' => 'jackets', 'ru' => 'куртка', 'ruPlural' => 'куртки', 'gender' => 'f'],
            ['en' => 'coat', 'plural' => 'coats', 'ru' => 'пальто', 'ruPlural' => 'пальто', 'gender' => 'n'],
            ['en' => 'sock', 'plural' => 'socks', 'ru' => 'носок', 'ruPlural' => 'носки', 'gender' => 'm'],
            ['en' => 'shoe', 'plural' => 'shoes', 'ru' => 'туфля', 'ruPlural' => 'туфли', 'gender' => 'f'],
            ['en' => 'boot', 'plural' => 'boots', 'ru' => 'ботинок', 'ruPlural' => 'ботинки', 'gender' => 'm'],
            ['en' => 'cap', 'plural' => 'caps', 'ru' => 'кепка', 'ruPlural' => 'кепки', 'gender' => 'f'],
            ['en' => 'hat', 'plural' => 'hats', 'ru' => 'шляпа', 'ruPlural' => 'шляпы', 'gender' => 'f'],
            ['en' => 'glove', 'plural' => 'gloves', 'ru' => 'перчатка', 'ruPlural' => 'перчатки', 'gender' => 'f'],
            ['en' => 'belt', 'plural' => 'belts', 'ru' => 'ремень', 'ruPlural' => 'ремни', 'gender' => 'm'],
            ['en' => 'pocket', 'plural' => 'pockets', 'ru' => 'карман', 'ruPlural' => 'карманы', 'gender' => 'm'],
            ['en' => 'button', 'plural' => 'buttons', 'ru' => 'пуговица', 'ruPlural' => 'пуговицы', 'gender' => 'f'],
            ['en' => 'sweater', 'plural' => 'sweaters', 'ru' => 'свитер', 'ruPlural' => 'свитеры', 'gender' => 'm'],
            ['en' => 'blouse', 'plural' => 'blouses', 'ru' => 'блузка', 'ruPlural' => 'блузки', 'gender' => 'f'],
            ['en' => 'phone', 'plural' => 'phones', 'ru' => 'телефон', 'ruPlural' => 'телефоны', 'gender' => 'm'],
            ['en' => 'tablet', 'plural' => 'tablets', 'ru' => 'планшет', 'ruPlural' => 'планшеты', 'gender' => 'm'],
            ['en' => 'laptop', 'plural' => 'laptops', 'ru' => 'ноутбук', 'ruPlural' => 'ноутбуки', 'gender' => 'm'],
            ['en' => 'computer', 'plural' => 'computers', 'ru' => 'компьютер', 'ruPlural' => 'компьютеры', 'gender' => 'm'],
            ['en' => 'screen', 'plural' => 'screens', 'ru' => 'экран', 'ruPlural' => 'экраны', 'gender' => 'm'],
            ['en' => 'camera', 'plural' => 'cameras', 'ru' => 'камера', 'ruPlural' => 'камеры', 'gender' => 'f'],
            ['en' => 'radio', 'plural' => 'radios', 'ru' => 'радио', 'ruPlural' => 'радио', 'gender' => 'n'],
            ['en' => 'speaker', 'plural' => 'speakers', 'ru' => 'колонка', 'ruPlural' => 'колонки', 'gender' => 'f'],
            ['en' => 'cable', 'plural' => 'cables', 'ru' => 'кабель', 'ruPlural' => 'кабели', 'gender' => 'm'],
            ['en' => 'charger', 'plural' => 'chargers', 'ru' => 'зарядное устройство', 'ruPlural' => 'зарядные устройства', 'gender' => 'n'],
            ['en' => 'keyboard', 'plural' => 'keyboards', 'ru' => 'клавиатура', 'ruPlural' => 'клавиатуры', 'gender' => 'f'],
            ['en' => 'printer', 'plural' => 'printers', 'ru' => 'принтер', 'ruPlural' => 'принтеры', 'gender' => 'm'],
            ['en' => 'monitor', 'plural' => 'monitors', 'ru' => 'монитор', 'ruPlural' => 'мониторы', 'gender' => 'm'],
            ['en' => 'battery', 'plural' => 'batteries', 'ru' => 'батарейка', 'ruPlural' => 'батарейки', 'gender' => 'f'],
            ['en' => 'cat', 'plural' => 'cats', 'ru' => 'кошка', 'ruPlural' => 'кошки', 'gender' => 'f'],
            ['en' => 'dog', 'plural' => 'dogs', 'ru' => 'собака', 'ruPlural' => 'собаки', 'gender' => 'f'],
            ['en' => 'rabbit', 'plural' => 'rabbits', 'ru' => 'кролик', 'ruPlural' => 'кролики', 'gender' => 'm'],
            ['en' => 'hamster', 'plural' => 'hamsters', 'ru' => 'хомяк', 'ruPlural' => 'хомяки', 'gender' => 'm'],
            ['en' => 'parrot', 'plural' => 'parrots', 'ru' => 'попугай', 'ruPlural' => 'попугаи', 'gender' => 'm'],
            ['en' => 'turtle', 'plural' => 'turtles', 'ru' => 'черепаха', 'ruPlural' => 'черепахи', 'gender' => 'f'],
            ['en' => 'horse', 'plural' => 'horses', 'ru' => 'лошадь', 'ruPlural' => 'лошади', 'gender' => 'f'],
            ['en' => 'donkey', 'plural' => 'donkeys', 'ru' => 'осёл', 'ruPlural' => 'ослы', 'gender' => 'm'],
            ['en' => 'goat', 'plural' => 'goats', 'ru' => 'коза', 'ruPlural' => 'козы', 'gender' => 'f'],
            ['en' => 'cow', 'plural' => 'cows', 'ru' => 'корова', 'ruPlural' => 'коровы', 'gender' => 'f'],
            ['en' => 'pig', 'plural' => 'pigs', 'ru' => 'свинья', 'ruPlural' => 'свиньи', 'gender' => 'f'],
            ['en' => 'duck', 'plural' => 'ducks', 'ru' => 'утка', 'ruPlural' => 'утки', 'gender' => 'f'],
            ['en' => 'chicken', 'plural' => 'chickens', 'ru' => 'курица', 'ruPlural' => 'курицы', 'gender' => 'f'],
            ['en' => 'monkey', 'plural' => 'monkeys', 'ru' => 'обезьяна', 'ruPlural' => 'обезьяны', 'gender' => 'f'],
            ['en' => 'tiger', 'plural' => 'tigers', 'ru' => 'тигр', 'ruPlural' => 'тигры', 'gender' => 'm'],
            ['en' => 'lion', 'plural' => 'lions', 'ru' => 'лев', 'ruPlural' => 'львы', 'gender' => 'm'],
            ['en' => 'panda', 'plural' => 'pandas', 'ru' => 'панда', 'ruPlural' => 'панды', 'gender' => 'f'],
            ['en' => 'zebra', 'plural' => 'zebras', 'ru' => 'зебра', 'ruPlural' => 'зебры', 'gender' => 'f'],
            ['en' => 'frog', 'plural' => 'frogs', 'ru' => 'лягушка', 'ruPlural' => 'лягушки', 'gender' => 'f'],
            ['en' => 'snake', 'plural' => 'snakes', 'ru' => 'змея', 'ruPlural' => 'змеи', 'gender' => 'f'],
        ];
    }
}
