<?php
// Builds sql/seed.sql, the book covers and the room plans.
// Run from the project root:  C:\xampp\php\php.exe tools\build-seed.php
// Sample texts are public domain works from Project Gutenberg (downloaded once into tools/cache).
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/covers.php';

const WORDS_PER_PAGE = 125;
const SAMPLE_PAGES = 10;

$categories = [
    ['Fiction', 'fiction', 'Novels about people, choices and the lives they make.'],
    ['Mystery', 'mystery', 'Detectives, locked rooms and clues hiding in plain sight.'],
    ['Science Fiction', 'science-fiction', 'Time machines, invaders and the stories that invented the future.'],
    ['Non Fiction', 'non-fiction', 'True stories and big ideas that shaped how we think.'],
    ['Young Readers', 'young-readers', 'Adventures for younger readers, and for everyone who still loves them.'],
    ['Classics', 'classics', 'The long, rich novels that every reader meets eventually.'],
];

// [gutenberg id, start markers, title, author, category slug, year, pages, price, rating, stock,
//  staff pick, featured, days since added, hook, synopsis]
$books = [
    // Fiction
    [1342, ['It is a truth universally acknowledged, that a single man'], 'Pride and Prejudice', 'Jane Austen', 'fiction', 1813, 432, 12.90, 4.7, 18, 0, 0, 64,
     'Five sisters, one proud gentleman, and a first impression that gets everything wrong.',
     'When the wealthy Mr Bingley rents Netherfield Park, the Bennet household is thrown into happy chaos. Elizabeth, the sharpest of five sisters, takes an instant dislike to his friend Mr Darcy. Austen\'s comedy of manners is funny, warm and exact about how pride and quick judgement can blind even the cleverest people.'],
    [1260, ['There was no possibility of taking a walk that day'], 'Jane Eyre', 'Charlotte Brontë', 'fiction', 1847, 532, 13.90, 4.6, 12, 1, 0, 88,
     'An orphan governess, a house full of secrets, and a voice that refuses to be small.',
     'Jane grows up unloved at Gateshead and survives the harsh school at Lowood before taking a post as governess at Thornfield Hall. There she meets the brooding Mr Rochester and hears strange laughter in the night. A fierce, intimate story about independence, conscience and what it costs to be true to yourself.'],
    [514, ['be Christmas without any presents'], 'Little Women', 'Louisa May Alcott', 'fiction', 1868, 449, 11.90, 4.5, 20, 0, 0, 120,
     'Four sisters, one winter without their father, and the dreams that carry them through.',
     'Meg, Jo, Beth and Amy March grow up in Massachusetts while their father serves in the Civil War. Money is tight, tempers are short, and each sister has her own idea of a good life. Alcott\'s much loved novel is funny and tender about family, ambition and growing up.'],
    [64317, ['In my younger and more vulnerable years'], 'The Great Gatsby', 'F. Scott Fitzgerald', 'fiction', 1925, 208, 12.50, 4.4, 15, 0, 0, 2,
     'Green light, gold parties, and a man who believes he can repeat the past.',
     'Nick Carraway rents a small house on Long Island next door to the mysterious Jay Gatsby, whose parties are the talk of the summer of 1922. Gatsby wants one thing: Daisy Buchanan, Nick\'s cousin, who lives across the bay. A short, dazzling novel about longing, money and the American dream.'],
    [174, ['The studio was filled with the rich odour of roses'], 'The Picture of Dorian Gray', 'Oscar Wilde', 'fiction', 1890, 254, 10.90, 4.4, 9, 0, 0, 45,
     'A portrait ages so its subject never has to. What could go wrong?',
     'Young Dorian Gray is painted by an artist who adores him and charmed by a friend who dazzles him. When he wishes that his portrait would grow old instead of him, the wish comes true. Wilde\'s only novel is witty, gothic and sharply modern about vanity and consequence.'],
    [768, ['I have just returned from a visit to my landlord'], 'Wuthering Heights', 'Emily Brontë', 'fiction', 1847, 416, 11.50, 4.2, 7, 0, 0, 150,
     'On the Yorkshire moors, a love so wild it haunts two generations.',
     'A stranger taking shelter at Wuthering Heights hears the story of Heathcliff, a foundling taken in by the Earnshaw family, and his passionate bond with Catherine. Their love turns to a revenge that reaches into the next generation. Stormy, strange and unforgettable.'],
    // Mystery
    [1661, ['To Sherlock Holmes she is always'], 'The Adventures of Sherlock Holmes', 'Arthur Conan Doyle', 'mystery', 1892, 307, 12.90, 4.8, 22, 0, 0, 70,
     'Twelve cases, one violin, and the sharpest mind on Baker Street.',
     'From a king\'s blackmail problem to a red headed man paid to copy the encyclopaedia, these twelve stories show Sherlock Holmes at his best, told by his loyal friend Dr Watson. Short, clever and endlessly rereadable, this is the perfect first Holmes.'],
    [2852, ['Mr. Sherlock Holmes, who was usually very late in the mornings'], 'The Hound of the Baskervilles', 'Arthur Conan Doyle', 'mystery', 1902, 256, 11.90, 4.7, 14, 1, 0, 95,
     'A family curse, a lonely moor, and the footprints of a gigantic hound.',
     'When Sir Charles Baskerville is found dead on Dartmoor with a look of terror on his face, rumours of a spectral hound return. Holmes sends Watson to protect the young heir while he investigates. The most atmospheric Holmes novel, and one of the great mysteries.'],
    [244, ['In the year 1878 I took my degree'], 'A Study in Scarlet', 'Arthur Conan Doyle', 'mystery', 1887, 188, 9.90, 4.3, 10, 0, 0, 132,
     'The case where Holmes and Watson first meet, and a single word is written in blood.',
     'Recently home from war, Dr Watson needs cheap lodgings and is introduced to an eccentric flatmate. Soon he is drawn into the case of a body found in an empty house in Brixton. The first Sherlock Holmes story, with a surprising second half set in the American West.'],
    [863, ['The intense interest aroused in the public'], 'The Mysterious Affair at Styles', 'Agatha Christie', 'mystery', 1920, 296, 11.90, 4.4, 11, 0, 0, 4,
     'Hercule Poirot\'s first case: poison, a country house, and far too many suspects.',
     'Recovering from war wounds, Captain Hastings visits Styles Court just before its wealthy owner dies of strychnine poisoning. Luckily a Belgian refugee with a magnificent moustache is staying nearby. Agatha Christie\'s debut introduces Hercule Poirot and sets the rules for the classic whodunit.'],
    [155, ['I address these lines'], 'The Moonstone', 'Wilkie Collins', 'mystery', 1868, 528, 13.50, 4.3, 0, 0, 0, 160,
     'A sacred diamond, a birthday gift, and a theft no one can explain.',
     'A great yellow diamond taken from an Indian shrine is left to Rachel Verinder on her eighteenth birthday. By morning it is gone. Told by a series of witnesses, each with their own blind spots, this is often called the first English detective novel, and it is still one of the most enjoyable.'],
    [204, ['Between the silver ribbon of morning'], 'The Innocence of Father Brown', 'G. K. Chesterton', 'mystery', 1911, 272, 10.50, 4.2, 6, 0, 0, 9,
     'A small, round priest who understands criminals because he listens to them.',
     'Father Brown looks harmless, carries an umbrella and is always underestimated. In twelve playful and surprising stories he solves crimes through his understanding of human nature, often while the great detective Valentin is still looking for clues. Clever puzzles with a warm heart.'],
    // Science Fiction
    [84, ['You will rejoice to hear that no disaster'], 'Frankenstein', 'Mary Shelley', 'science-fiction', 1818, 280, 14.90, 4.6, 16, 0, 1, 30,
     'A young scientist builds a life, then runs from it. Two hundred years later, we still argue about who the monster is.',
     'Victor Frankenstein discovers how to give life to dead matter and creates a being, then abandons it in horror. Alone and rejected, the creature learns to speak, read and feel, and demands to know why he was made. Told through letters from an Arctic voyage, Shelley\'s novel invented science fiction and still asks the hardest questions about ambition and responsibility.'],
    [35, ['The Time Traveller (for so it will be convenient to speak of him)'], 'The Time Machine', 'H. G. Wells', 'science-fiction', 1895, 118, 8.90, 4.3, 19, 1, 0, 58,
     'A Victorian inventor travels to the year 802,701 and does not like what he finds.',
     'Over dinner, the Time Traveller tells his guests a story they can hardly believe: a journey into the far future, where humanity has split into two strange races. Short and gripping, Wells\'s first novel is both an adventure and a sharp warning about class and complacency.'],
    [36, ['No one would have believed in the last years'], 'The War of the Worlds', 'H. G. Wells', 'science-fiction', 1898, 192, 9.90, 4.4, 13, 0, 0, 77,
     'No one would have believed it. Then the cylinders landed.',
     'A cylinder falls on Horsell Common, and whatever is unscrewing its lid is not from Earth. Within days, Martian fighting machines stride across southern England. Told by an ordinary man trying to get home to his wife, this is the original alien invasion story and still one of the most tense.'],
    [164, ['The year 1866 was signalised by a remarkable incident', 'The year 1866 was marked by a bizarre development'], 'Twenty Thousand Leagues Under the Seas', 'Jules Verne', 'science-fiction', 1870, 426, 12.90, 4.3, 8, 0, 0, 6,
     'Aboard the Nautilus, a mysterious captain shows three captives the wonders of the deep.',
     'A professor, his servant and a harpooner hunt a sea monster and find a submarine instead. Captain Nemo keeps them aboard the Nautilus for a voyage past coral forests, sunken ruins and the South Pole. A classic adventure full of wonder, with a captain nobody forgets.'],
    [5230, ['The stranger came early in February'], 'The Invisible Man', 'H. G. Wells', 'science-fiction', 1897, 160, 8.90, 4.1, 10, 0, 0, 110,
     'A stranger arrives in a snowstorm, wrapped in bandages. He is hiding more than his face.',
     'The village of Iping is curious about the bandaged lodger at the Coach and Horses. When the truth comes out, a brilliant scientist who has made himself invisible turns from eccentric to dangerous. A fast, darkly funny thriller about power without accountability.'],
    [62, ['I am a very old man'], 'A Princess of Mars', 'Edgar Rice Burroughs', 'science-fiction', 1912, 186, 9.50, 3.9, 9, 0, 0, 140,
     'A Civil War soldier wakes up on Mars, where he can leap like a giant.',
     'John Carter falls asleep in an Arizona cave and wakes on Barsoom, a dying Mars of green warriors, red cities and ancient canals. Stronger in the lighter gravity, he fights, falls in love with Dejah Thoris and changes a world. Pure pulp adventure that inspired generations of science fiction.'],
    // Non Fiction
    [2680, ['Of my grandfather Verus', 'From my grandfather Verus'], 'Meditations', 'Marcus Aurelius', 'non-fiction', 180, 254, 10.90, 4.6, 24, 1, 0, 50,
     'The private notebook of a Roman emperor, trying every day to be a better person.',
     'Written in camp during military campaigns and never meant for publication, the Meditations are Marcus Aurelius reminding himself how to live: calmly, justly, and with attention to what is in his control. Short entries, easy to dip into, and surprisingly useful for modern life.'],
    [205, ['When I wrote the following pages'], 'Walden', 'Henry David Thoreau', 'non-fiction', 1854, 352, 11.90, 4.1, 8, 0, 0, 8,
     'Two years in a cabin by a pond, and a lifetime of questions about how to live.',
     'In 1845 Thoreau built a small cabin by Walden Pond and lived there simply, to see what life really required. His account mixes practical detail, close observation of nature and sharp criticism of busy modern life. A founding book of the simple living and environmental movements.'],
    [132, ['said: The art of war is of vital importance'], 'The Art of War', 'Sun Tzu', 'non-fiction', -500, 122, 7.90, 4.4, 30, 0, 0, 100,
     'Thirteen short chapters on strategy that people still quote in boardrooms.',
     'Attributed to the general Sun Tzu, this ancient Chinese text sets out how to win with the least fighting: know yourself, know your opponent, and choose your ground. Translated by Lionel Giles, it is brief, direct and endlessly applied to business, sport and everyday decisions.'],
    [23, ['I was born in Tuckahoe'], 'Narrative of the Life of Frederick Douglass', 'Frederick Douglass', 'non-fiction', 1845, 144, 8.90, 4.8, 12, 1, 0, 36,
     'Born into slavery, he taught himself to read and wrote his way to freedom.',
     'Frederick Douglass describes his childhood on a Maryland plantation, the cruelty he witnessed, and the moment he understood that reading was the path to freedom. Written after his escape, this powerful and precise memoir became one of the most important books of the abolitionist movement.'],
    [148, ['I have ever had pleasure in obtaining'], 'The Autobiography of Benjamin Franklin', 'Benjamin Franklin', 'non-fiction', 1791, 196, 9.90, 4.2, 11, 0, 0, 125,
     'A printer\'s apprentice becomes a scientist, writer and founder, and tells you how.',
     'Franklin writes to his son about his rise from a runaway apprentice in Boston to a successful printer in Philadelphia, including his famous plan to practise thirteen virtues. Frank, practical and often funny, it is one of the first great self improvement books.'],
    [1228, ['When on board H.M.S.'], 'On the Origin of Species', 'Charles Darwin', 'non-fiction', 1859, 502, 15.90, 4.5, 6, 0, 0, 3,
     'The book that explained how life changes, one small variation at a time.',
     'Drawing on pigeons, barnacles, fossils and his voyage on the Beagle, Darwin argues that species evolve through natural selection. Careful, modest and packed with evidence, the Origin changed science forever and is more readable than its reputation suggests.'],
    // Young Readers
    [11, ['Alice was beginning to get very tired'], 'Alice\'s Adventures in Wonderland', 'Lewis Carroll', 'young-readers', 1865, 128, 9.90, 4.6, 25, 0, 0, 40,
     'Down the rabbit hole, where nothing makes sense and everything is curious.',
     'Bored on a riverbank, Alice follows a White Rabbit with a pocket watch and tumbles into Wonderland. There she grows and shrinks, argues with a caterpillar, attends a mad tea party and plays croquet with flamingos. Playful nonsense that readers of every age return to.'],
    [16, ['All children, except one, grow up'], 'Peter Pan', 'J. M. Barrie', 'young-readers', 1911, 192, 9.50, 4.4, 17, 0, 0, 84,
     'Second star to the right, and straight on till morning.',
     'One night Peter Pan flies into the Darling children\'s nursery looking for his shadow, and takes Wendy, John and Michael to Neverland. There are mermaids, lost boys and Captain Hook, who has never forgiven Peter for his hand. Magical, funny and a little sad about growing up.'],
    [55, ['Dorothy lived in the midst of the great Kansas prairies'], 'The Wonderful Wizard of Oz', 'L. Frank Baum', 'young-readers', 1900, 154, 9.50, 4.3, 14, 0, 0, 5,
     'A cyclone, a yellow brick road, and three friends looking for what they already have.',
     'A Kansas cyclone carries Dorothy and her dog Toto to the land of Oz. To get home she must reach the Emerald City, and on the way she meets a Scarecrow, a Tin Woodman and a Cowardly Lion, each with a wish of their own. The original American fairy tale.'],
    [113, ['When Mary Lennox was sent to Misselthwaite Manor'], 'The Secret Garden', 'Frances Hodgson Burnett', 'young-readers', 1911, 288, 10.50, 4.5, 13, 1, 0, 66,
     'A locked garden, a hidden key, and a lonely girl who brings them both back to life.',
     'Orphaned Mary Lennox is sent to her uncle\'s gloomy house on the Yorkshire moors. She hears crying in the corridors and learns of a garden that has been locked for ten years. As Mary finds her way in, the garden and the people around her begin to heal.'],
    [120, ['Squire Trelawney, Dr. Livesey, and the rest'], 'Treasure Island', 'Robert Louis Stevenson', 'young-readers', 1883, 292, 10.90, 4.5, 16, 0, 0, 105,
     'A map, a one legged cook, and the sea story that invented pirates as we know them.',
     'When an old sea captain dies at the Admiral Benbow inn, young Jim Hawkins finds a treasure map in his chest. Soon he is sailing to a distant island with a crew that includes the charming and dangerous Long John Silver. Adventure storytelling at its very best.'],
    [289, ['The Mole had been working very hard all the morning'], 'The Wind in the Willows', 'Kenneth Grahame', 'young-readers', 1908, 256, 10.50, 4.4, 12, 0, 0, 11,
     'Messing about in boats with Mole, Rat, Badger and the terribly excitable Toad.',
     'Mole abandons his spring cleaning and discovers the river, where he befriends the Water Rat. Together with wise Badger they try to keep the reckless Mr Toad out of trouble, with limited success. Gentle, funny and full of the English countryside.'],
    // Classics
    [1400, ['family name being Pirrip'], 'Great Expectations', 'Charles Dickens', 'classics', 1861, 544, 13.90, 4.5, 10, 1, 0, 90,
     'A boy meets a convict in a graveyard, and his whole life changes.',
     'Pip, an orphan raised by his sister and her kind husband Joe, is given money by a secret benefactor and sent to London to become a gentleman. Along the way he meets the bitter Miss Havisham and the beautiful, cold Estella. Dickens at his finest: funny, dark and deeply moving.'],
    [345, ['Left Munich at'], 'Dracula', 'Bram Stoker', 'classics', 1897, 418, 12.90, 4.5, 15, 0, 0, 48,
     'A solicitor travels to Transylvania to sell a house. His client has other plans.',
     'Jonathan Harker visits Count Dracula\'s castle to finalise a property sale and soon realises he is a prisoner. When the Count arrives in England, a small group led by Professor Van Helsing must stop him. Told through diaries, letters and newspaper clippings, it is still genuinely creepy.'],
    [2701, ['Call me Ishmael'], 'Moby Dick', 'Herman Melville', 'classics', 1851, 720, 15.90, 4.0, 8, 0, 0, 170,
     'Call me Ishmael. One captain, one white whale, one obsession.',
     'Ishmael signs on to the whaling ship Pequod and discovers that Captain Ahab has one goal: to hunt down the white whale that took his leg. Part adventure, part encyclopaedia of whaling, part philosophy, Melville\'s huge novel is strange, ambitious and unlike anything else.'],
    [98, ['It was the best of times'], 'A Tale of Two Cities', 'Charles Dickens', 'classics', 1859, 448, 12.90, 4.4, 9, 0, 0, 115,
     'The best of times, the worst of times: London, Paris and a revolution.',
     'Doctor Manette is released after eighteen years in the Bastille and reunited with his daughter Lucie in London. But the French Revolution is coming, and their lives become tangled with an aristocrat, a lawyer who has given up on himself, and Madame Defarge, who never forgets.'],
    [2554, ['On an exceptionally hot evening early in July'], 'Crime and Punishment', 'Fyodor Dostoevsky', 'classics', 1866, 576, 14.50, 4.6, 7, 0, 0, 1,
     'A student commits a murder to prove a theory, then has to live with himself.',
     'Raskolnikov, poor and feverish in St Petersburg, convinces himself that an extraordinary man may break moral law. After he kills a pawnbroker, guilt, fear and a patient detective close in. A psychological thriller and one of the great novels of conscience, in the translation by Constance Garnett.'],
    [219, ['The Nellie, a cruising yawl'], 'Heart of Darkness', 'Joseph Conrad', 'classics', 1899, 96, 8.50, 4.0, 11, 1, 0, 135,
     'A journey up the Congo river to find a man who has gone too far.',
     'On a boat on the Thames, Marlow tells of his voyage into Central Africa for a Belgian trading company, and of Kurtz, the ivory agent everyone talks about. A short, intense and troubling novella about colonialism and the darkness inside people who call themselves civilised.'],
];

// Member submissions waiting for approval: [gid, markers, title, author, cat, year, pages, price, stock, added_by email, hook, synopsis]
$pending = [
    [45, ['Mrs. Rachel Lynde lived just where the Avonlea main road'], 'Anne of Green Gables', 'L. M. Montgomery', 'young-readers', 1908, 320, 10.90, 6, 'aisha@localhost',
     'They asked for a boy. They got Anne, with an e.',
     'Elderly siblings Matthew and Marilla Cuthbert send for an orphan boy to help on their farm on Prince Edward Island, and a talkative red haired girl arrives instead. Anne\'s imagination, mistakes and big heart slowly win over the whole town of Avonlea.'],
    [236, ['of a very warm evening in the Seeonee hills'], 'The Jungle Book', 'Rudyard Kipling', 'young-readers', 1894, 212, 9.90, 5, 'ben@localhost',
     'A boy raised by wolves, taught by a bear and a panther, hunted by a tiger.',
     'Mowgli is adopted by a wolf pack in the Indian jungle and taught its law by Baloo the bear and Bagheera the panther, while Shere Khan the tiger waits. The book also includes other animal tales, such as the brave mongoose Rikki-tikki-tavi.'],
];

$users = [
    ['Grace Lim', 'admin@localhost', 'Admin123!', 'admin', 400],
    ['Aisha Rahman', 'aisha@localhost', 'Member123!', 'member', 210],
    ['Ben Tan', 'ben@localhost', 'Member123!', 'member', 180],
    ['Chloe Wong', 'chloe@localhost', 'Member123!', 'member', 150],
    ['Daniel Ng', 'daniel@localhost', 'Member123!', 'member', 90],
    ['Elena Koh', 'elena@localhost', 'Member123!', 'member', 60],
    ['Farah Ismail', 'farah@localhost', 'Member123!', 'member', 40],
    ['Gavin Lee', 'gavin@localhost', 'Member123!', 'member', 21],
    ['Hana Sato', 'hana@localhost', 'Member123!', 'member', 7],
];

$rooms = [
    ['Folio', 2, 'Level 2', 'whiteboard, power'],
    ['Quill', 2, 'Level 2', 'power'],
    ['Atlas', 4, 'Level 3', 'whiteboard, power, screen'],
    ['Sonnet', 4, 'Level 3', 'whiteboard, power'],
    ['Vellum', 6, 'Level 4', 'whiteboard, power, screen'],
    ['Colophon', 6, 'Level 4', 'power, screen'],
];

// Downloads (once) and returns the plain text of a Project Gutenberg book.
function gutenberg_text(int $id): string
{
    $cache = __DIR__ . "/cache/pg$id.txt";
    if (!is_file($cache)) {
        @mkdir(__DIR__ . '/cache', 0777, true);
        $text = @file_get_contents("https://www.gutenberg.org/cache/epub/$id/pg$id.txt");
        if ($text === false || strlen($text) < 1000) {
            throw new RuntimeException("Could not download Gutenberg book $id");
        }
        file_put_contents($cache, $text);
    }
    return file_get_contents($cache);
}

// Cuts the licence header and footer and normalises line endings and quotes.
function strip_gutenberg(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (preg_match('/\*\*\*\s*START OF (THE|THIS) PROJECT GUTENBERG[^\n]*\n/i', $text, $m, PREG_OFFSET_CAPTURE)) {
        $text = substr($text, $m[0][1] + strlen($m[0][0]));
    }
    if (preg_match('/\*\*\*\s*END OF (THE|THIS) PROJECT GUTENBERG/i', $text, $m, PREG_OFFSET_CAPTURE)) {
        $text = substr($text, 0, $m[0][1]);
    }
    return $text;
}

// Returns the paragraphs of the opening of a book, starting at the paragraph with a marker.
function opening_paragraphs(string $text, array $markers): array
{
    $pos = false;
    foreach ($markers as $marker) {
        $pos = strpos($text, $marker);
        if ($pos === false) {
            $pos = stripos($text, $marker);
        }
        if ($pos !== false) {
            break;
        }
    }
    if ($pos === false) {
        throw new RuntimeException('Marker not found: ' . $markers[0]);
    }
    $start = strrpos(substr($text, 0, $pos), "\n\n");
    $start = $start === false ? 0 : $start;

    // Include one short heading paragraph just before the start (for example "CHAPTER I.").
    $before = rtrim(substr($text, 0, $start));
    $prevBreak = strrpos($before, "\n\n");
    $prev = trim(substr($before, $prevBreak === false ? 0 : $prevBreak));
    $prev = preg_replace('/\s+/', ' ', $prev);
    $heading = (mb_strlen($prev) > 2 && mb_strlen($prev) < 70 && !preg_match('/[a-z]\.$/', $prev)) ? $prev : '';

    $chunk = substr($text, $start, 60000);
    $paras = [];
    if ($heading !== '') {
        $paras[] = '# ' . clean_text($heading);
    }
    foreach (preg_split('/\n\s*\n/', $chunk) as $p) {
        $p = clean_text(preg_replace('/\s+/', ' ', trim($p)));
        if ($p === '' || preg_match('/^\[(Illustration|Footnote)/i', $p)) {
            continue;
        }
        $isHeading = mb_strlen($p) < 70 && (preg_match('/^(CHAPTER|Chapter|BOOK|LETTER|Letter|STAVE|PART|PROLOGUE|[IVXLC]+\.?\s*$)/', $p)
            || (mb_strtoupper($p) === $p && preg_match('/[A-Z]/', $p)));
        $paras[] = $isHeading ? '# ' . $p : $p;
    }
    return $paras;
}

// Removes Gutenberg markup such as _italics_ and [Illustration] notes.
function clean_text(string $p): string
{
    $p = preg_replace('/\[Illustration[^\]]*\]/i', '', $p);
    $p = preg_replace('/^\[|\]$/', '', trim($p));
    $p = preg_replace('/\[\d+\]/', '', $p);
    $p = preg_replace('/(?<![A-Za-z0-9])_([^_]+)_(?![A-Za-z0-9])/', '$1', $p);
    $p = str_replace('_', '', $p);
    return trim($p);
}

// Packs paragraphs into pages of roughly WORDS_PER_PAGE words.
function paginate(array $paras): array
{
    $limit = WORDS_PER_PAGE;
    $pages = [];
    $page = [];
    $count = 0;
    $hasText = false;
    $queue = $paras;
    while ($queue && count($pages) < SAMPLE_PAGES) {
        $p = array_shift($queue);
        if (str_starts_with($p, '# ')) {
            // A heading never sits at the bottom of a page.
            if ($count > $limit * 0.8) {
                $pages[] = $page;
                [$page, $count, $hasText] = [[], 0, false];
            }
            $page[] = $p;
            $count += 4;
            continue;
        }
        $words = str_word_count($p);
        if ($count + $words <= $limit * 1.1) {
            $page[] = $p;
            $count += $words;
            $hasText = true;
            continue;
        }
        // Fill the rest of this page with whole sentences and carry the remainder over.
        [$first, $rest] = split_sentences($p, $limit - $count, !$hasText);
        if ($first !== '') {
            $page[] = $first;
        }
        if ($rest !== '') {
            array_unshift($queue, $rest);
        }
        $pages[] = $page;
        [$page, $count, $hasText] = [[], 0, false];
    }
    if ($hasText && count($pages) < SAMPLE_PAGES) {
        $pages[] = $page;
    }
    return array_map(fn($pg) => implode("\n\n", $pg), array_slice($pages, 0, SAMPLE_PAGES));
}

// Splits a paragraph into the sentences that fit in $room words and the rest.
function split_sentences(string $p, int $room, bool $force): array
{
    $sentences = preg_split('/(?<=[.!?;:]|[.!?;:]["\'”’])\s+/u', $p);
    $first = [];
    $taken = 0;
    while ($sentences && $taken + str_word_count($sentences[0]) <= $room) {
        $taken += str_word_count($sentences[0]);
        $first[] = array_shift($sentences);
    }
    if (!$first && $force && $sentences) {
        $first[] = array_shift($sentences);
    }
    return [implode(' ', $first), implode(' ', $sentences)];
}

// Quotes a value for SQL.
function q(mixed $v): string
{
    if ($v === null) {
        return 'NULL';
    }
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $v) . "'";
}

// ---------------------------------------------------------------------------------------------
$sql = [];
$sql[] = "-- BookNest seed data. Generated by tools/build-seed.php on " . date('Y-m-d H:i');
$sql[] = "-- Dates are relative to the moment of import, so the demo always looks current.";
$sql[] = "-- Sample texts: public domain works courtesy of Project Gutenberg (www.gutenberg.org).";
$sql[] = "USE booknest;\nSET NAMES utf8mb4;\nSET time_zone = '+08:00';\n";

// Categories
$catIds = [];
$rows = [];
foreach ($categories as $i => [$name, $slug, $blurb]) {
    $catIds[$slug] = $i + 1;
    $rows[] = '(' . ($i + 1) . ', ' . q($name) . ', ' . q($slug) . ', ' . q($blurb) . ', ' . ($i + 1) . ')';
}
$sql[] = "INSERT INTO categories (id, name, slug, blurb, sort_order) VALUES\n  " . implode(",\n  ", $rows) . ";\n";
$catNames = array_combine(array_column($categories, 1), array_column($categories, 0));

// Users
$userIds = [];
$rows = [];
foreach ($users as $i => [$name, $email, $pw, $role, $daysAgo]) {
    $userIds[$email] = $i + 1;
    $rows[] = '(' . ($i + 1) . ', ' . q($name) . ', ' . q($email) . ', ' . q(password_hash($pw, PASSWORD_DEFAULT))
        . ', ' . q($role) . ", NOW() - INTERVAL $daysAgo DAY)";
}
$sql[] = "INSERT INTO users (id, full_name, email, password_hash, role, created_at) VALUES\n  " . implode(",\n  ", $rows) . ";\n";

// Books (approved, then pending)
$all = [];
foreach ($books as $b) {
    $all[] = ['gid' => $b[0], 'markers' => $b[1], 'title' => $b[2], 'author' => $b[3], 'cat' => $b[4], 'year' => $b[5],
              'pages' => $b[6], 'price' => $b[7], 'rating' => $b[8], 'stock' => $b[9], 'staff' => $b[10], 'featured' => $b[11],
              'days' => $b[12], 'hook' => $b[13], 'synopsis' => $b[14], 'status' => 'approved', 'by' => null];
}
foreach ($pending as $b) {
    $all[] = ['gid' => $b[0], 'markers' => $b[1], 'title' => $b[2], 'author' => $b[3], 'cat' => $b[4], 'year' => $b[5],
              'pages' => $b[6], 'price' => $b[7], 'rating' => 0.0, 'stock' => $b[8], 'staff' => 0, 'featured' => 0,
              'days' => 1, 'hook' => $b[10], 'synopsis' => $b[11], 'status' => 'pending', 'by' => $userIds[$b[9]]];
}

$rows = [];
$report = [];
foreach ($all as $i => $b) {
    $id = $i + 1;
    $serial = sprintf('BNT-%06d', 100 + $id);
    $pages = paginate(opening_paragraphs(strip_gutenberg(gutenberg_text($b['gid'])), $b['markers']));
    $sample = implode("\n---PAGE---\n", $pages);
    $cover = write_cover($serial, $b['title'], $b['author'], $id, $catNames[$b['cat']]);
    $alt = 'Cover of ' . $b['title'] . ' by ' . $b['author'] . ': title lettering on a geometric design';
    $report[] = sprintf('%2d %s %-42s pages=%d first="%s"', $id, $serial, mb_substr($b['title'], 0, 42), count($pages),
        mb_substr(str_replace("\n", ' ', $pages[0] ?? ''), 0, 60));
    $rows[] = '(' . implode(', ', [$id, q($serial), q($b['title']), q($b['author']), $catIds[$b['cat']], q($b['hook']),
        q($b['synopsis']), q($sample), $b['price'], $b['rating'], $b['year'], $b['pages'], $b['stock'], q($cover), q($alt),
        $b['featured'], $b['staff'], q($b['status']), $b['by'] ?? 'NULL', "NOW() - INTERVAL {$b['days']} DAY - INTERVAL " . ($id * 37 % 600) . ' MINUTE']) . ')';
}
$sql[] = "INSERT INTO books (id, serial_no, title, author, category_id, hook, synopsis, sample_text, price, rating, published_year, pages, stock, cover_path, cover_alt, is_featured, is_staff_pick, status, added_by, created_at) VALUES\n  "
    . implode(",\n  ", $rows) . ";\n";

// Rooms and their floor plans
$rows = [];
@mkdir(APP_ROOT . '/assets/img/rooms', 0777, true);
foreach ($rooms as $i => [$name, $cap, $floor, $features]) {
    $path = 'img/rooms/' . strtolower($name) . '.svg';
    file_put_contents(APP_ROOT . '/assets/' . $path, room_svg($name, $cap, $features, $i + 1));
    $rows[] = '(' . ($i + 1) . ', ' . q($name) . ", $cap, " . q($floor) . ', ' . q($features) . ', ' . q($path) . ', 1)';
}
$sql[] = "INSERT INTO study_rooms (id, name, capacity, floor, features, image_path, is_active) VALUES\n  " . implode(",\n  ", $rows) . ";\n";

// Orders over the last 21 days (deterministic, so the seed is reproducible)
mt_srand(4727);
$approved = array_values(array_filter(array_keys($all), fn($k) => $all[$k]['status'] === 'approved'));
$members = array_slice(array_values($userIds), 1);
$guests = [['Priya Nair', 'priya@localhost'], ['Marcus Chen', 'marcus@localhost'], ['Siti Aminah', 'siti@localhost'], ['Tom Reyes', 'tom@localhost']];
$orderRows = [];
$itemRows = [];
$orderId = 0;
for ($d = 20; $d >= 0; $d--) {
    $n = mt_rand(0, 3) + ($d < 14 ? 1 : 0);
    for ($k = 0; $k < $n; $k++) {
        $orderId++;
        $isGuest = mt_rand(1, 4) === 1;
        if ($isGuest) {
            [$name, $email] = $guests[mt_rand(0, count($guests) - 1)];
            $uid = null;
        } else {
            $uid = $members[mt_rand(0, count($members) - 1)];
            [$name, $email] = [$users[$uid - 1][0], $users[$uid - 1][1]];
        }
        $lines = mt_rand(1, 3);
        $picked = [];
        $subtotal = 0;
        for ($l = 0; $l < $lines; $l++) {
            // Weighted toward the first books in each category so "top 5" has clear winners.
            $bk = $approved[min(count($approved) - 1, (int) floor((mt_rand(0, 1000) / 1000) ** 1.6 * count($approved)))];
            if (isset($picked[$bk])) {
                continue;
            }
            $qty = mt_rand(1, 4) === 1 ? 2 : 1;
            $picked[$bk] = $qty;
            $subtotal += $all[$bk]['price'] * $qty;
            $itemRows[] = "($orderId, " . ($bk + 1) . ", $qty, {$all[$bk]['price']})";
        }
        $method = mt_rand(0, 1) ? 'delivery' : 'pickup';
        $fee = ($method === 'delivery' && $subtotal < FREE_DELIVERY_FROM) ? DELIVERY_FEE : 0;
        $status = mt_rand(1, 9) === 1 ? 'failed' : 'paid';
        $orderRows[] = '(' . implode(', ', [$orderId, $uid ?? 'NULL', q($email), q($name), q('9' . mt_rand(1000000, 8999999)),
            q($method), $method === 'delivery' ? q(mt_rand(1, 99) . ' Jurong West Street ' . mt_rand(11, 99) . ', Singapore 6' . mt_rand(40000, 49999)) : 'NULL',
            'NULL', number_format($subtotal, 2, '.', ''), number_format($fee, 2, '.', ''), number_format($subtotal + $fee, 2, '.', ''),
            q($status), "NOW() - INTERVAL $d DAY - INTERVAL " . mt_rand(30, 600) . ' MINUTE']) . ')';
    }
}
$sql[] = "INSERT INTO orders (id, user_id, email, full_name, phone, delivery_method, address, note, subtotal, delivery_fee, total, payment_status, created_at) VALUES\n  "
    . implode(",\n  ", $orderRows) . ";\n";
$sql[] = "INSERT INTO order_items (order_id, book_id, qty, unit_price) VALUES\n  " . implode(",\n  ", $itemRows) . ";\n";

// Room bookings from 14 days ago to 3 days ahead, respecting every room rule.
$bookRows = [];
$aisha = $userIds['aisha@localhost'];
$ben = $userIds['ben@localhost'];
for ($d = -14; $d <= 3; $d++) {
    $roomBusy = [];
    $userMinutes = [];
    $userBusy = [];
    $wanted = mt_rand(7, 12);
    if ($d === 0) {
        // Ben has used his hour today, so the daily cap can be demonstrated with his account.
        $bookRows[] = "(1, $ben, CURDATE(), '10:00:00', '11:00:00', 'Group project', 'confirmed', NOW() - INTERVAL 1 DAY)";
        $roomBusy[1][] = [600, 660];
        $userMinutes[$ben] = 60;
        $userBusy[$ben][] = [600, 660];
    }
    if ($d === 1) {
        // Aisha's upcoming booking tomorrow, shown as "Yours" and cancellable in My Account.
        $bookRows[] = "(1, $aisha, CURDATE() + INTERVAL 1 DAY, '14:00:00', '15:00:00', 'Revision for finals', 'confirmed', NOW())";
        $roomBusy[1][] = [840, 900];
        $userMinutes[$aisha] = 60;
        $userBusy[$aisha][] = [840, 900];
    }
    for ($t = 0; $t < $wanted * 3 && $wanted > 0; $t++) {
        $room = mt_rand(1, 6);
        $uid = $members[mt_rand(0, count($members) - 1)];
        if ($d === 0 && $uid === $aisha) {
            continue; // keep Aisha's quota free today for the live demo
        }
        $hourWeights = [10, 11, 12, 13, 14, 14, 15, 15, 15, 16, 16, 17, 18, 19, 20];
        $startMin = $hourWeights[mt_rand(0, count($hourWeights) - 1)] * 60 + (mt_rand(0, 1) ? 30 : 0);
        $dur = mt_rand(0, 2) ? 60 : 30;
        $end = $startMin + $dur;
        if ($end > CLOSE_HOUR * 60 || ($userMinutes[$uid] ?? 0) + $dur > DAILY_CAP_MINUTES) {
            continue;
        }
        $clash = false;
        foreach ($roomBusy[$room] ?? [] as [$s, $e]) {
            $clash = $clash || ($s < $end && $e > $startMin);
        }
        foreach ($userBusy[$uid] ?? [] as [$s, $e]) {
            $clash = $clash || ($s < $end && $e > $startMin);
        }
        if ($clash) {
            continue;
        }
        $status = mt_rand(1, 10) === 1 ? 'cancelled' : 'confirmed';
        if ($status === 'confirmed') {
            $roomBusy[$room][] = [$startMin, $end];
            $userBusy[$uid][] = [$startMin, $end];
            $userMinutes[$uid] = ($userMinutes[$uid] ?? 0) + $dur;
        }
        $date = $d === 0 ? 'CURDATE()' : ('CURDATE() ' . ($d < 0 ? '- INTERVAL ' . (-$d) : '+ INTERVAL ' . $d) . ' DAY');
        $purposes = ['Group project', 'Quiet study', 'Interview practice', 'Book club', 'Tutoring', 'Presentation rehearsal', null];
        $bookRows[] = "($room, $uid, $date, '" . from_min($startMin) . ":00', '" . from_min($end) . ":00', "
            . q($purposes[mt_rand(0, count($purposes) - 1)]) . ", '$status', NOW() - INTERVAL " . (15 - $d) . ' DAY)';
        if (--$wanted <= 0) {
            break;
        }
    }
}
$sql[] = "INSERT INTO room_bookings (room_id, user_id, booking_date, start_time, end_time, purpose, status, created_at) VALUES\n  "
    . implode(",\n  ", $bookRows) . ";\n";

// Shelves
$shelf = [[$aisha, 1], [$aisha, 13], [$aisha, 25], [$aisha, 32], [$ben, 7], [$ben, 14], [$userIds['chloe@localhost'], 2]];
$sql[] = "INSERT INTO shelf (user_id, book_id, added_at) VALUES\n  "
    . implode(",\n  ", array_map(fn($s) => "({$s[0]}, {$s[1]}, NOW() - INTERVAL " . ($s[1] % 9) . ' DAY)', $shelf)) . ";\n";

file_put_contents(APP_ROOT . '/sql/seed.sql', implode("\n", $sql));

// Converts minutes after midnight to "HH:MM".
function from_min(int $m): string
{
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

echo implode("\n", $report), "\n\nWrote sql/seed.sql (", count($all), " books, $orderId orders, ", count($bookRows), " bookings)\n";
