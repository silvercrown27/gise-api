<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseMentor;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CourseCatalogSeeder extends Seeder
{
    /**
     * Category definitions, matching frontend/src/data/categories.ts.
     */
    private array $categories = [
        [
            'slug' => 'software-engineering',
            'name' => 'Software Engineering',
            'description' => 'Full-stack development, mobile apps and cloud-native systems.',
        ],
        [
            'slug' => 'data-and-ai',
            'name' => 'Data & Artificial Intelligence',
            'description' => 'Data analytics, machine learning and applied AI engineering.',
        ],
        [
            'slug' => 'electrical-and-electronics',
            'name' => 'Electrical & Electronics Engineering',
            'description' => 'Circuit design, embedded systems and power electronics.',
        ],
        [
            'slug' => 'civil-and-construction',
            'name' => 'Civil & Construction Engineering',
            'description' => 'Structural design, site management and CAD-based modelling.',
        ],
        [
            'slug' => 'mechanical-and-robotics',
            'name' => 'Mechanical & Robotics Engineering',
            'description' => 'Machine design, automation and robotics prototyping.',
        ],
        [
            'slug' => 'mathematics-and-cybersecurity',
            'name' => 'Mathematics & Cybersecurity',
            'description' => 'Applied mathematics, cryptography and network security.',
        ],
    ];

    /**
     * Course definitions, matching frontend/src/data/courses.ts.
     */
    private array $courses = [
        [
            'slug' => 'full-stack-web-development',
            'code' => 'CRS-01',
            'category' => 'software-engineering',
            'title' => 'Full-Stack Web Development',
            'tagline' => 'HTML, CSS, JavaScript and modern frameworks.',
            'description' => 'Build and deploy three live web applications by graduation. Go from fundamentals to production-ready React and Node.js applications, with real deployment pipelines and code review from working engineers.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'beginner_friendly',
            'spine' => 'green',
            'durationWeeks' => 8,
            'price' => 42000,
            'originalPrice' => 52000,
            'seatsLeft' => 21,
            'nextCohort' => '2026-09-07',
            'mode' => 'hybrid',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'HTML, CSS, JavaScript fundamentals'],
                ['week' => 'Weeks 3-4', 'topic' => 'React and component-driven UI'],
                ['week' => 'Weeks 5-6', 'topic' => 'Node.js, APIs and databases'],
                ['week' => 'Weeks 7-8', 'topic' => 'Deployment, testing and capstone project'],
            ],
            'outcomes' => [
                'Ship three deployed full-stack applications',
                'Work confidently with React, Node.js and SQL',
                'Understand Git workflows and code review',
            ],
            'mentor' => ['name' => 'Brian Otieno', 'role' => 'Senior Software Engineer'],
        ],
        [
            'slug' => 'mobile-app-development',
            'code' => 'CRS-02',
            'category' => 'software-engineering',
            'title' => 'Mobile App Development',
            'tagline' => 'Native-quality apps with React Native.',
            'description' => 'Design, build and ship cross-platform mobile apps for Android and iOS. Learn state management, device APIs and how to publish to app stores.',
            'image' => 'mobile-app-development.jpg',
            'level' => 'intermediate',
            'tag' => 'high_demand',
            'spine' => 'blue',
            'durationWeeks' => 10,
            'price' => 48000,
            'originalPrice' => null,
            'seatsLeft' => 18,
            'nextCohort' => '2026-09-14',
            'mode' => 'online',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'React Native fundamentals and navigation'],
                ['week' => 'Weeks 3-5', 'topic' => 'State management and device APIs'],
                ['week' => 'Weeks 6-8', 'topic' => 'Backend integration and push notifications'],
                ['week' => 'Weeks 9-10', 'topic' => 'App store submission and capstone'],
            ],
            'outcomes' => [
                'Publish a cross-platform app to Play Store / TestFlight',
                'Integrate device hardware and third-party APIs',
                'Manage app state at production scale',
            ],
            'mentor' => ['name' => 'Faith Wanjiru', 'role' => 'Mobile Engineering Lead'],
        ],
        [
            'slug' => 'cloud-and-devops-engineering',
            'code' => 'CRS-03',
            'category' => 'software-engineering',
            'title' => 'Cloud & DevOps Engineering',
            'tagline' => 'AWS, containers and CI/CD pipelines.',
            'description' => 'Learn to design, deploy and operate cloud infrastructure. Master Docker, Kubernetes fundamentals, Infrastructure-as-Code and CI/CD automation.',
            'image' => 'cloud-and-devops-engineering.jpg',
            'level' => 'career_switch',
            'tag' => 'career_switch',
            'spine' => 'black',
            'durationWeeks' => 10,
            'price' => 54000,
            'originalPrice' => null,
            'seatsLeft' => 15,
            'nextCohort' => '2026-10-05',
            'mode' => 'online',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'Linux, networking and cloud fundamentals'],
                ['week' => 'Weeks 3-5', 'topic' => 'Docker, Kubernetes and container orchestration'],
                ['week' => 'Weeks 6-8', 'topic' => 'CI/CD pipelines and Infrastructure-as-Code'],
                ['week' => 'Weeks 9-10', 'topic' => 'Monitoring, security and capstone deployment'],
            ],
            'outcomes' => [
                'Automate deployments with CI/CD pipelines',
                'Operate containerised workloads on AWS',
                'Write Infrastructure-as-Code with Terraform',
            ],
            'mentor' => ['name' => 'Kevin Mwangi', 'role' => 'Cloud Infrastructure Engineer'],
        ],
        [
            'slug' => 'data-analytics',
            'code' => 'CRS-04',
            'category' => 'data-and-ai',
            'title' => 'Data Analytics',
            'tagline' => 'Excel to SQL to Python.',
            'description' => 'Turn messy datasets into decisions and finish with a portfolio of real analyses. Learn statistics, SQL, Python and dashboarding tools used by working analysts.',
            'image' => 'data-analytics.jpg',
            'level' => 'beginner',
            'tag' => 'high_demand',
            'spine' => 'blue',
            'durationWeeks' => 8,
            'price' => 39000,
            'originalPrice' => 45000,
            'seatsLeft' => 24,
            'nextCohort' => '2026-09-07',
            'mode' => 'hybrid',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'Excel, statistics and data cleaning'],
                ['week' => 'Weeks 3-4', 'topic' => 'SQL for analytics'],
                ['week' => 'Weeks 5-6', 'topic' => 'Python, Pandas and visualisation'],
                ['week' => 'Weeks 7-8', 'topic' => 'Dashboards and capstone analysis'],
            ],
            'outcomes' => [
                'Build a portfolio of real-world data analyses',
                'Query and model data confidently in SQL and Python',
                'Design dashboards that drive business decisions',
            ],
            'mentor' => ['name' => 'Purity Nyambura', 'role' => 'Lead Data Analyst'],
        ],
        [
            'slug' => 'machine-learning-and-ai-engineering',
            'code' => 'CRS-05',
            'category' => 'data-and-ai',
            'title' => 'Machine Learning & AI Engineering',
            'tagline' => 'Applied ML from notebooks to production.',
            'description' => 'Build, evaluate and deploy machine learning models. Cover supervised and unsupervised learning, neural networks, and how to serve models in production APIs.',
            'image' => 'machine-learning-and-ai-engineering.jpg',
            'level' => 'advanced',
            'tag' => 'high_demand',
            'spine' => 'bright',
            'durationWeeks' => 12,
            'price' => 62000,
            'originalPrice' => null,
            'seatsLeft' => 12,
            'nextCohort' => '2026-10-12',
            'mode' => 'online',
            'curriculum' => [
                ['week' => 'Weeks 1-3', 'topic' => 'Python for ML and statistical foundations'],
                ['week' => 'Weeks 4-6', 'topic' => 'Supervised and unsupervised learning'],
                ['week' => 'Weeks 7-9', 'topic' => 'Neural networks and deep learning'],
                ['week' => 'Weeks 10-12', 'topic' => 'Model deployment and capstone project'],
            ],
            'outcomes' => [
                'Train, evaluate and tune ML models',
                'Deploy models behind production APIs',
                'Understand deep learning fundamentals',
            ],
            'mentor' => ['name' => 'Dennis Kiptoo', 'role' => 'Machine Learning Engineer'],
        ],
        [
            'slug' => 'data-science-and-statistics',
            'code' => 'CRS-06',
            'category' => 'data-and-ai',
            'title' => 'Data Science & Applied Statistics',
            'tagline' => 'Rigorous statistical methods for real decisions.',
            'description' => 'A quantitative deep-dive into experimental design, statistical inference and predictive modelling for STEM-driven teams.',
            'image' => 'data-science-and-statistics.jpg',
            'level' => 'intermediate',
            'tag' => 'portfolio_track',
            'spine' => 'blue',
            'durationWeeks' => 10,
            'price' => 50000,
            'originalPrice' => null,
            'seatsLeft' => 16,
            'nextCohort' => '2026-09-21',
            'mode' => 'hybrid',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'Probability and statistical inference'],
                ['week' => 'Weeks 3-5', 'topic' => 'Experimental design and A/B testing'],
                ['week' => 'Weeks 6-8', 'topic' => 'Predictive modelling with R and Python'],
                ['week' => 'Weeks 9-10', 'topic' => 'Communicating results and capstone'],
            ],
            'outcomes' => [
                'Design sound experiments and interpret results',
                'Build predictive models with statistical rigour',
                'Present findings to technical and non-technical audiences',
            ],
            'mentor' => ['name' => 'Grace Achieng', 'role' => 'Applied Statistician'],
        ],
        [
            'slug' => 'embedded-systems-and-iot',
            'code' => 'CRS-07',
            'category' => 'electrical-and-electronics',
            'title' => 'Embedded Systems & IoT',
            'tagline' => 'Microcontrollers, sensors and connected devices.',
            'description' => 'Design and program embedded systems using microcontrollers, sensors and IoT protocols. Build connected hardware prototypes from breadboard to enclosure.',
            'image' => 'embedded-systems-and-iot.jpg',
            'level' => 'intermediate',
            'tag' => 'career_switch',
            'spine' => 'black',
            'durationWeeks' => 10,
            'price' => 55000,
            'originalPrice' => null,
            'seatsLeft' => 14,
            'nextCohort' => '2026-10-05',
            'mode' => 'in_person',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'Circuits, microcontrollers and C programming'],
                ['week' => 'Weeks 3-5', 'topic' => 'Sensors, actuators and PCB basics'],
                ['week' => 'Weeks 6-8', 'topic' => 'IoT protocols and connectivity'],
                ['week' => 'Weeks 9-10', 'topic' => 'Prototype build and capstone demo'],
            ],
            'outcomes' => [
                'Program microcontrollers for real-world sensing',
                'Design basic PCBs and read schematics',
                'Build an end-to-end connected IoT prototype',
            ],
            'mentor' => ['name' => 'Samuel Kariuki', 'role' => 'Electronics & IoT Engineer'],
        ],
        [
            'slug' => 'power-systems-engineering',
            'code' => 'CRS-08',
            'category' => 'electrical-and-electronics',
            'title' => 'Power Systems Engineering',
            'tagline' => 'Power distribution, renewable energy and grid design.',
            'description' => 'Study electrical power generation, transmission and distribution, with a focus on renewable energy integration and grid stability.',
            'image' => 'power-systems-engineering.jpg',
            'level' => 'advanced',
            'tag' => 'high_demand',
            'spine' => 'green',
            'durationWeeks' => 12,
            'price' => 58000,
            'originalPrice' => null,
            'seatsLeft' => 11,
            'nextCohort' => '2026-10-19',
            'mode' => 'in_person',
            'curriculum' => [
                ['week' => 'Weeks 1-3', 'topic' => 'Power generation and transmission fundamentals'],
                ['week' => 'Weeks 4-6', 'topic' => 'Distribution networks and grid protection'],
                ['week' => 'Weeks 7-9', 'topic' => 'Renewable energy integration'],
                ['week' => 'Weeks 10-12', 'topic' => 'Grid stability analysis and capstone'],
            ],
            'outcomes' => [
                'Analyse power distribution and transmission networks',
                'Design renewable-energy grid integration plans',
                'Apply protection and stability principles to real grids',
            ],
            'mentor' => ['name' => 'Peter Mutiso', 'role' => 'Power Systems Engineer'],
        ],
        [
            'slug' => 'structural-engineering-and-design',
            'code' => 'CRS-09',
            'category' => 'civil-and-construction',
            'title' => 'Structural Engineering & Design',
            'tagline' => 'Structural analysis with real-world CAD tools.',
            'description' => 'Learn structural analysis, materials science and CAD-based design for buildings and infrastructure, taught by practicing structural engineers.',
            'image' => 'structural-engineering-and-design.jpg',
            'level' => 'intermediate',
            'tag' => 'portfolio_track',
            'spine' => 'black',
            'durationWeeks' => 10,
            'price' => 52000,
            'originalPrice' => null,
            'seatsLeft' => 17,
            'nextCohort' => '2026-09-21',
            'mode' => 'hybrid',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'Statics, materials and load analysis'],
                ['week' => 'Weeks 3-5', 'topic' => 'Structural analysis methods'],
                ['week' => 'Weeks 6-8', 'topic' => 'CAD modelling with AutoCAD and Revit'],
                ['week' => 'Weeks 9-10', 'topic' => 'Design project and capstone review'],
            ],
            'outcomes' => [
                'Perform structural load and stress analysis',
                'Model buildings in industry-standard CAD tools',
                'Produce a portfolio-ready structural design project',
            ],
            'mentor' => ['name' => 'Esther Chebet', 'role' => 'Structural Engineer'],
        ],
        [
            'slug' => 'construction-project-management',
            'code' => 'CRS-10',
            'category' => 'civil-and-construction',
            'title' => 'Construction Project Management',
            'tagline' => 'Planning, budgeting and site delivery.',
            'description' => 'Manage construction projects from planning to handover. Cover scheduling, budgeting, procurement and site safety management.',
            'image' => 'construction-project-management.jpg',
            'level' => 'beginner',
            'tag' => 'leadership',
            'spine' => 'green',
            'durationWeeks' => 8,
            'price' => 45000,
            'originalPrice' => null,
            'seatsLeft' => 20,
            'nextCohort' => '2026-09-14',
            'mode' => 'hybrid',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'Project planning and scheduling'],
                ['week' => 'Weeks 3-4', 'topic' => 'Budgeting, procurement and contracts'],
                ['week' => 'Weeks 5-6', 'topic' => 'Site safety and quality management'],
                ['week' => 'Weeks 7-8', 'topic' => 'Stakeholder management and capstone'],
            ],
            'outcomes' => [
                'Plan and schedule multi-phase construction projects',
                'Manage budgets, procurement and contracts',
                'Apply site safety and quality standards',
            ],
            'mentor' => ['name' => 'James Ndungu', 'role' => 'Construction Project Manager'],
        ],
        [
            'slug' => 'mechanical-design-and-cad',
            'code' => 'CRS-11',
            'category' => 'mechanical-and-robotics',
            'title' => 'Mechanical Design & CAD',
            'tagline' => 'SolidWorks, machine design and manufacturing.',
            'description' => 'Design mechanical components and assemblies using industry-standard CAD tools, with a focus on manufacturability and materials selection.',
            'image' => 'mechanical-design-and-cad.jpg',
            'level' => 'beginner',
            'tag' => 'beginner_friendly',
            'spine' => 'blue',
            'durationWeeks' => 8,
            'price' => 44000,
            'originalPrice' => null,
            'seatsLeft' => 19,
            'nextCohort' => '2026-09-07',
            'mode' => 'hybrid',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'CAD fundamentals with SolidWorks'],
                ['week' => 'Weeks 3-4', 'topic' => 'Machine design and tolerancing'],
                ['week' => 'Weeks 5-6', 'topic' => 'Materials selection and manufacturing methods'],
                ['week' => 'Weeks 7-8', 'topic' => 'Assembly design and capstone project'],
            ],
            'outcomes' => [
                'Design manufacturable parts and assemblies in CAD',
                'Apply GD&T and tolerancing standards',
                'Select materials and processes for production',
            ],
            'mentor' => ['name' => 'Collins Omondi', 'role' => 'Mechanical Design Engineer'],
        ],
        [
            'slug' => 'robotics-and-automation',
            'code' => 'CRS-12',
            'category' => 'mechanical-and-robotics',
            'title' => 'Robotics & Automation',
            'tagline' => 'Build and program autonomous robots.',
            'description' => 'Design, build and program robots from the ground up. Cover kinematics, control systems, sensors and ROS-based software.',
            'image' => 'robotics-and-automation.jpg',
            'level' => 'advanced',
            'tag' => 'high_demand',
            'spine' => 'bright',
            'durationWeeks' => 12,
            'price' => 60000,
            'originalPrice' => null,
            'seatsLeft' => 13,
            'nextCohort' => '2026-10-12',
            'mode' => 'in_person',
            'curriculum' => [
                ['week' => 'Weeks 1-3', 'topic' => 'Kinematics and mechanical design'],
                ['week' => 'Weeks 4-6', 'topic' => 'Control systems and actuators'],
                ['week' => 'Weeks 7-9', 'topic' => 'Sensors, perception and ROS'],
                ['week' => 'Weeks 10-12', 'topic' => 'Autonomous navigation and capstone build'],
            ],
            'outcomes' => [
                'Design and build a working robot prototype',
                'Program control and perception systems with ROS',
                'Apply automation principles to real-world tasks',
            ],
            'mentor' => ['name' => 'Anthony Mbugua', 'role' => 'Robotics Engineer'],
        ],
        [
            'slug' => 'cybersecurity-fundamentals',
            'code' => 'CRS-13',
            'category' => 'mathematics-and-cybersecurity',
            'title' => 'Cybersecurity Fundamentals',
            'tagline' => 'Networks, threat analysis and hands-on labs.',
            'description' => 'Practice in safe, simulated environments from day one. Learn network security, ethical hacking fundamentals, cryptography and incident response.',
            'image' => 'cybersecurity-fundamentals.jpg',
            'level' => 'career_switch',
            'tag' => 'career_switch',
            'spine' => 'blue',
            'durationWeeks' => 10,
            'price' => 51000,
            'originalPrice' => null,
            'seatsLeft' => 22,
            'nextCohort' => '2026-09-28',
            'mode' => 'online',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'Networking fundamentals and security principles'],
                ['week' => 'Weeks 3-5', 'topic' => 'Threat analysis and ethical hacking basics'],
                ['week' => 'Weeks 6-8', 'topic' => 'Cryptography and secure system design'],
                ['week' => 'Weeks 9-10', 'topic' => 'Incident response and capstone lab'],
            ],
            'outcomes' => [
                'Identify and analyse common network threats',
                'Apply cryptography to secure systems',
                'Respond to security incidents using a structured process',
            ],
            'mentor' => ['name' => 'Linet Wambui', 'role' => 'Cybersecurity Analyst'],
        ],
        [
            'slug' => 'applied-mathematics-for-engineers',
            'code' => 'CRS-14',
            'category' => 'mathematics-and-cybersecurity',
            'title' => 'Applied Mathematics for Engineers',
            'tagline' => 'The quantitative core behind every STEM discipline.',
            'description' => 'Strengthen the mathematical foundations behind engineering and data work: calculus, linear algebra, differential equations and numerical methods.',
            'image' => 'applied-mathematics-for-engineers.jpg',
            'level' => 'beginner',
            'tag' => 'beginner_friendly',
            'spine' => 'black',
            'durationWeeks' => 8,
            'price' => 36000,
            'originalPrice' => null,
            'seatsLeft' => 26,
            'nextCohort' => '2026-09-07',
            'mode' => 'online',
            'curriculum' => [
                ['week' => 'Weeks 1-2', 'topic' => 'Calculus and linear algebra refresher'],
                ['week' => 'Weeks 3-4', 'topic' => 'Differential equations for engineers'],
                ['week' => 'Weeks 5-6', 'topic' => 'Numerical methods and simulation'],
                ['week' => 'Weeks 7-8', 'topic' => 'Applied projects and capstone'],
            ],
            'outcomes' => [
                'Apply calculus and linear algebra to engineering problems',
                'Solve differential equations numerically',
                'Build confidence for advanced STEM coursework',
            ],
            'mentor' => ['name' => 'Dr. Naomi Cherono', 'role' => 'Applied Mathematician'],
        ],
    ];

    public function run(): void
    {
        $categoryIds = $this->seedCategories();
        $this->seedCourses($categoryIds);
    }

    private function seedCategories(): array
    {
        $ids = [];

        foreach ($this->categories as $categoryData) {
            $category = Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                ]
            );

            $ids[$categoryData['slug']] = $category->id;
        }

        return $ids;
    }

    private function seedCourses(array $categoryIds): void
    {
        foreach ($this->courses as $courseData) {
            $instructor = $this->findOrCreateInstructor($courseData['mentor']);

            $course = Course::updateOrCreate(
                ['slug' => $courseData['slug']],
                [
                    'instructor_id' => $instructor->id,
                    'category_id' => $categoryIds[$courseData['category']] ?? null,
                    'code' => $courseData['code'],
                    'title' => $courseData['title'],
                    'tagline' => $courseData['tagline'],
                    'short_description' => $courseData['tagline'],
                    'full_description' => $courseData['description'],
                    'outline' => ['outcomes' => $courseData['outcomes']],
                    'thumbnail_url' => '/storage/courses/' . $courseData['image'],
                    'price' => $courseData['price'],
                    'original_price' => $courseData['originalPrice'],
                    'currency' => 'KES',
                    'status' => 'published',
                    'level' => $courseData['level'],
                    'tag' => $courseData['tag'],
                    'spine' => $courseData['spine'],
                    'mode' => $courseData['mode'],
                    'duration_weeks' => $courseData['durationWeeks'],
                    'language' => 'en',
                    'published_at' => now(),
                ]
            );

            $this->seedModules($course, $courseData['curriculum']);
            $this->seedCohort($course, $courseData);
            $this->seedMentor($course, $instructor);
        }
    }

    private function findOrCreateInstructor(array $mentor): User
    {
        $email = Str::slug($mentor['name']) . '@scholarcompass.test';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $mentor['name'],
                'password' => Hash::make(Str::random(24)),
                'email_verified_at' => now(),
            ]
        );

        ScholarUser::updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => 'instructor',
                'status' => 'active',
            ]
        );

        InstructorProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'bio' => "{$mentor['name']} is a {$mentor['role']} and mentor at ScholarCompass.",
                'expertise_tags' => $mentor['role'],
                'verification_status' => 'verified',
            ]
        );

        return $user;
    }

    private function seedModules(Course $course, array $curriculum): void
    {
        foreach ($curriculum as $index => $item) {
            CourseModule::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'order_index' => $index,
                ],
                [
                    'title' => "{$item['week']}: {$item['topic']}",
                ]
            );
        }
    }

    private function seedCohort(Course $course, array $courseData): void
    {
        $startDate = $courseData['nextCohort'];
        $label = date('M d, Y', strtotime($startDate));

        Cohort::updateOrCreate(
            [
                'course_id' => $course->id,
                'start_date' => $startDate,
            ],
            [
                'label' => $label,
                'mode' => $courseData['mode'],
                'capacity' => $courseData['seatsLeft'] + 5,
                'seats_taken' => 5,
                'status' => 'upcoming',
            ]
        );
    }

    private function seedMentor(Course $course, User $instructor): void
    {
        CourseMentor::updateOrCreate(
            [
                'course_id' => $course->id,
                'mentor_id' => $instructor->id,
            ],
            [
                'assigned_at' => now(),
            ]
        );
    }
}
