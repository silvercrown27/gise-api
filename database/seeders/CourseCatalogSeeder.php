<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseLesson;
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
            'slug' => 'gis-and-mapping',
            'name' => 'GIS & Mapping',
            'description' => 'Geographic information systems, spatial analysis and remote sensing.',
        ],
        [
            'slug' => 'data-collection-and-management',
            'name' => 'Data Collection & Management',
            'description' => 'Mobile data collection, research design and statistical analysis.',
        ],
        [
            'slug' => 'monitoring-and-programme-management',
            'name' => 'Monitoring & Programme Management',
            'description' => 'Monitoring & evaluation, disaster risk and health programme management.',
        ],
        [
            'slug' => 'finance-and-operations',
            'name' => 'Finance & Operations',
            'description' => 'Grants management, financial auditing and supply chain operations.',
        ],
    ];

    /**
     * Course definitions. Each `objectives` bullet becomes part of the
     * instructor-authored `full_description` document (Introduction, Course
     * Objectives, Duration, Target Audience), and `curriculum` becomes real
     * course_modules/course_lessons rows rather than free-text.
     */
    private array $courses = [
        [
            'slug' => 'gis-and-spatial-analysis-for-agriculture-and-food-security',
            'code' => 'CRS-01',
            'category' => 'gis-and-mapping',
            'title' => 'GIS and Spatial Analysis for Agriculture and Food Security',
            'tagline' => 'Map crop yields, food insecurity and land use with GIS.',
            'shortDescription' => 'Use GIS and spatial analysis to monitor food security, plan agricultural interventions and track land-use change.',
            'introduction' => 'Agricultural and food security programmes increasingly rely on spatial data to identify where interventions are needed most. This course introduces GIS as a practical decision-support tool for agriculture and food security work, covering everything from collecting field data to building maps that communicate clearly to programme teams and donors.',
            'audience' => 'Agriculture officers, food security analysts, M&E staff and GIS beginners working in development or government agencies.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'beginner_friendly',
            'spine' => 'green',
            'durationWeeks' => 5,
            'price' => 350,
            'originalPrice' => 420,
            'seatsLeft' => 18,
            'nextCohort' => '2026-09-07',
            'mode' => 'hybrid',
            'objectives' => [
                'Understand core GIS concepts and coordinate systems used in agricultural mapping',
                'Collect, clean and manage spatial data relevant to food security',
                'Produce yield, land-use and vulnerability maps using QGIS',
                'Present spatial findings clearly in reports and dashboards',
            ],
            'curriculum' => [
                [
                    'title' => 'Foundations of GIS for Agriculture',
                    'lessons' => [
                        ['title' => 'Introduction to GIS and spatial thinking', 'minutes' => 45],
                        ['title' => 'Coordinate systems and map projections', 'minutes' => 40],
                    ],
                ],
                [
                    'title' => 'Spatial Data Collection and Management',
                    'lessons' => [
                        ['title' => 'Sourcing satellite and survey data', 'minutes' => 50],
                        ['title' => 'Cleaning and structuring spatial datasets', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Mapping Food Security and Land Use',
                    'lessons' => [
                        ['title' => 'Building crop and land-use maps in QGIS', 'minutes' => 55],
                        ['title' => 'Vulnerability and food-security mapping', 'minutes' => 50],
                        ['title' => 'Presenting spatial results to stakeholders', 'minutes' => 35],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Dennis Kariuki', 'role' => 'GIS and Remote Sensing Specialist'],
        ],
        [
            'slug' => 'gis-for-natural-resource-management',
            'code' => 'CRS-02',
            'category' => 'gis-and-mapping',
            'title' => 'GIS for Natural Resource Management',
            'tagline' => 'Track forests, water and land resources with GIS tools.',
            'shortDescription' => 'Apply GIS to monitor forests, water catchments and land degradation for sustainable resource management.',
            'introduction' => 'Natural resource managers are under growing pressure to make evidence-based decisions about forests, water and land. This course builds practical GIS skills for monitoring resource use, mapping degradation and supporting conservation planning with reliable, defensible data.',
            'audience' => 'Environmental officers, natural resource managers, conservation staff and researchers new to GIS.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'high_demand',
            'spine' => 'green',
            'durationWeeks' => 5,
            'price' => 350,
            'originalPrice' => null,
            'seatsLeft' => 16,
            'nextCohort' => '2026-09-14',
            'mode' => 'hybrid',
            'objectives' => [
                'Map forest cover, water catchments and land degradation over time',
                'Use satellite imagery to detect change in natural resources',
                'Build spatial layers to support conservation planning',
                'Communicate resource trends through maps and dashboards',
            ],
            'curriculum' => [
                [
                    'title' => 'GIS Foundations for Natural Resources',
                    'lessons' => [
                        ['title' => 'GIS concepts for environmental monitoring', 'minutes' => 45],
                        ['title' => 'Working with satellite imagery basics', 'minutes' => 40],
                    ],
                ],
                [
                    'title' => 'Monitoring Land, Forest and Water Resources',
                    'lessons' => [
                        ['title' => 'Mapping forest cover change', 'minutes' => 50],
                        ['title' => 'Delineating water catchments', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Analysis and Conservation Planning',
                    'lessons' => [
                        ['title' => 'Detecting land degradation over time', 'minutes' => 50],
                        ['title' => 'Building maps for conservation planning', 'minutes' => 45],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Dennis Kariuki', 'role' => 'GIS and Remote Sensing Specialist'],
        ],
        [
            'slug' => 'gis-for-disease-surveillance-monitoring',
            'code' => 'CRS-03',
            'category' => 'gis-and-mapping',
            'title' => 'GIS for Disease Surveillance and Monitoring',
            'tagline' => 'Track outbreaks and health trends with spatial analysis.',
            'shortDescription' => 'Learn to map and analyse disease outbreaks, health facility coverage and surveillance data using GIS.',
            'introduction' => 'Public health teams increasingly use maps to understand how disease spreads, where health services are lacking, and where to target response efforts. This workshop teaches the GIS skills needed to build disease surveillance maps and support faster, better-targeted health interventions.',
            'audience' => 'Public health officers, epidemiologists, surveillance teams and health programme staff.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'intermediate',
            'tag' => 'high_demand',
            'spine' => 'blue',
            'durationWeeks' => 5,
            'price' => 380,
            'originalPrice' => null,
            'seatsLeft' => 14,
            'nextCohort' => '2026-09-21',
            'mode' => 'online',
            'objectives' => [
                'Map disease incidence and outbreak clusters using GIS',
                'Assess health facility coverage and accessibility',
                'Build surveillance dashboards for ongoing monitoring',
                'Interpret spatial patterns to inform response planning',
            ],
            'curriculum' => [
                [
                    'title' => 'GIS Foundations for Public Health',
                    'lessons' => [
                        ['title' => 'Spatial epidemiology basics', 'minutes' => 45],
                        ['title' => 'Sourcing and structuring health data', 'minutes' => 40],
                    ],
                ],
                [
                    'title' => 'Mapping Outbreaks and Coverage',
                    'lessons' => [
                        ['title' => 'Mapping disease incidence and clusters', 'minutes' => 55],
                        ['title' => 'Health facility coverage analysis', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Surveillance Dashboards and Reporting',
                    'lessons' => [
                        ['title' => 'Building a surveillance dashboard', 'minutes' => 50],
                        ['title' => 'Communicating findings to response teams', 'minutes' => 35],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Faith Wanjiru', 'role' => 'Public Health GIS Analyst'],
        ],
        [
            'slug' => 'gis-data-collection-management-analysis-visualization-and-mapping',
            'code' => 'CRS-04',
            'category' => 'gis-and-mapping',
            'title' => 'GIS Data Collection, Management, Analysis, Visualization and Mapping',
            'tagline' => 'A complete, end-to-end GIS workflow for field teams.',
            'shortDescription' => 'Cover the full GIS workflow, from field data collection through analysis to publishing maps and dashboards.',
            'introduction' => 'This comprehensive training walks participants through the entire GIS workflow used in real projects: collecting spatial data in the field, organising it correctly, analysing it, and turning it into maps and visuals that decision-makers can actually use. It is designed for anyone who wants a solid, practical grounding across the whole GIS process rather than a single narrow skill.',
            'audience' => 'GIS technicians, field data officers, planners and researchers who need end-to-end GIS competency.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'intermediate',
            'tag' => 'portfolio_track',
            'spine' => 'blue',
            'durationWeeks' => 6,
            'price' => 410,
            'originalPrice' => 480,
            'seatsLeft' => 20,
            'nextCohort' => '2026-09-07',
            'mode' => 'hybrid',
            'objectives' => [
                'Collect accurate spatial data using field-ready GIS tools',
                'Organise and manage GIS datasets and metadata',
                'Perform spatial analysis to answer real programme questions',
                'Design clear, publication-ready maps and visualizations',
            ],
            'curriculum' => [
                [
                    'title' => 'Field Data Collection',
                    'lessons' => [
                        ['title' => 'Planning a spatial data collection exercise', 'minutes' => 45],
                        ['title' => 'Collecting GPS and attribute data in the field', 'minutes' => 50],
                    ],
                ],
                [
                    'title' => 'Data Management and Analysis',
                    'lessons' => [
                        ['title' => 'Organising GIS datasets and metadata', 'minutes' => 40],
                        ['title' => 'Core spatial analysis techniques', 'minutes' => 55],
                    ],
                ],
                [
                    'title' => 'Visualization and Mapping',
                    'lessons' => [
                        ['title' => 'Cartographic design principles', 'minutes' => 45],
                        ['title' => 'Publishing maps and interactive dashboards', 'minutes' => 50],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Dennis Kariuki', 'role' => 'GIS and Remote Sensing Specialist'],
        ],
        [
            'slug' => 'gis-and-mapping-in-crime-analysis',
            'code' => 'CRS-05',
            'category' => 'gis-and-mapping',
            'title' => 'GIS and Mapping in Crime Analysis',
            'tagline' => 'Map crime patterns to support safer, targeted policing.',
            'shortDescription' => 'Apply GIS techniques to analyse crime patterns, hotspots and trends to support evidence-based safety planning.',
            'introduction' => 'Crime analysis increasingly depends on spatial thinking: where incidents cluster, how patterns shift over time, and which areas need targeted resources. This seminar introduces GIS-based crime mapping techniques used by analysts to support safer, more effective community and law-enforcement planning.',
            'audience' => 'Crime analysts, security researchers, urban planners and civil-society safety programme staff.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'intermediate',
            'tag' => 'high_demand',
            'spine' => 'black',
            'durationWeeks' => 5,
            'price' => 380,
            'originalPrice' => null,
            'seatsLeft' => 12,
            'nextCohort' => '2026-09-28',
            'mode' => 'online',
            'objectives' => [
                'Map crime incidents and identify spatial hotspots',
                'Analyse crime trends over time and by location type',
                'Use spatial statistics to support resource allocation',
                'Present crime analysis findings to non-technical audiences',
            ],
            'curriculum' => [
                [
                    'title' => 'Foundations of Crime Mapping',
                    'lessons' => [
                        ['title' => 'GIS concepts for crime analysis', 'minutes' => 40],
                        ['title' => 'Preparing incident data for mapping', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Hotspot and Trend Analysis',
                    'lessons' => [
                        ['title' => 'Identifying crime hotspots', 'minutes' => 50],
                        ['title' => 'Analysing trends over time', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Reporting and Decision Support',
                    'lessons' => [
                        ['title' => 'Spatial statistics for resource planning', 'minutes' => 45],
                        ['title' => 'Presenting findings to stakeholders', 'minutes' => 35],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Faith Wanjiru', 'role' => 'Public Health GIS Analyst'],
        ],
        [
            'slug' => 'introduction-to-gis-using-arcgis-desktop',
            'code' => 'CRS-06',
            'category' => 'gis-and-mapping',
            'title' => 'Introduction to GIS Using ArcGIS Desktop',
            'tagline' => 'Get hands-on with the industry-standard GIS software.',
            'shortDescription' => 'A hands-on introduction to ArcGIS Desktop, covering data management, spatial analysis and map production.',
            'introduction' => 'ArcGIS Desktop remains one of the most widely used GIS platforms in government and development work. This introductory course takes participants from installing and navigating ArcGIS through to producing their own analysis and maps, building a solid foundation for further GIS work.',
            'audience' => 'Complete beginners to GIS software, and professionals transitioning from other mapping tools to ArcGIS.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'beginner_friendly',
            'spine' => 'green',
            'durationWeeks' => 10,
            'price' => 460,
            'originalPrice' => 520,
            'seatsLeft' => 22,
            'nextCohort' => '2026-09-07',
            'mode' => 'hybrid',
            'objectives' => [
                'Navigate the ArcGIS Desktop environment confidently',
                'Import, manage and edit spatial datasets',
                'Perform basic spatial analysis and geoprocessing',
                'Design and export professional maps',
            ],
            'curriculum' => [
                [
                    'title' => 'Getting Started with ArcGIS',
                    'lessons' => [
                        ['title' => 'ArcGIS interface and navigation', 'minutes' => 40],
                        ['title' => 'Loading and organising spatial data', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Editing and Managing Data',
                    'lessons' => [
                        ['title' => 'Creating and editing feature classes', 'minutes' => 50],
                        ['title' => 'Working with attribute tables', 'minutes' => 40],
                    ],
                ],
                [
                    'title' => 'Analysis and Map Production',
                    'lessons' => [
                        ['title' => 'Basic geoprocessing tools', 'minutes' => 55],
                        ['title' => 'Designing and exporting maps', 'minutes' => 45],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Dennis Kariuki', 'role' => 'GIS and Remote Sensing Specialist'],
        ],
        [
            'slug' => 'gis-for-health-sector-programme-management',
            'code' => 'CRS-07',
            'category' => 'monitoring-and-programme-management',
            'title' => 'GIS for Health Sector Programme Management',
            'tagline' => 'Plan and manage health programmes with spatial data.',
            'shortDescription' => 'Use GIS to plan facility placement, track service delivery and manage health programmes more effectively.',
            'introduction' => 'Health programme managers need to know where services are reaching people and where gaps remain. This course shows how GIS supports programme planning and management decisions, from facility siting to tracking service delivery across a project area.',
            'audience' => 'Health programme managers, planners and M&E officers working in health-sector projects.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'intermediate',
            'tag' => 'career_switch',
            'spine' => 'blue',
            'durationWeeks' => 5,
            'price' => 390,
            'originalPrice' => null,
            'seatsLeft' => 15,
            'nextCohort' => '2026-09-14',
            'mode' => 'online',
            'objectives' => [
                'Map health facility locations and catchment areas',
                'Track service delivery and coverage gaps spatially',
                'Use GIS outputs to support programme planning decisions',
                'Build recurring spatial reports for programme reviews',
            ],
            'curriculum' => [
                [
                    'title' => 'GIS Foundations for Health Programmes',
                    'lessons' => [
                        ['title' => 'Spatial thinking for programme managers', 'minutes' => 40],
                        ['title' => 'Mapping facility locations and catchments', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Tracking Coverage and Service Delivery',
                    'lessons' => [
                        ['title' => 'Identifying service delivery gaps', 'minutes' => 50],
                        ['title' => 'Combining GIS with programme indicators', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Reporting for Programme Management',
                    'lessons' => [
                        ['title' => 'Building recurring spatial reports', 'minutes' => 45],
                        ['title' => 'Presenting spatial evidence to donors', 'minutes' => 35],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Faith Wanjiru', 'role' => 'Public Health GIS Analyst'],
        ],
        [
            'slug' => 'gis-and-remote-sensing-for-sustainable-forestry',
            'code' => 'CRS-08',
            'category' => 'gis-and-mapping',
            'title' => 'GIS and Remote Sensing for Sustainable Forestry',
            'tagline' => 'Monitor forests and deforestation with satellite data.',
            'shortDescription' => 'Combine GIS and remote sensing to monitor forest cover, deforestation and sustainable forestry practices.',
            'introduction' => 'Sustainable forestry management depends on being able to see change over time, often across areas too large to survey on foot. This course pairs GIS with remote sensing techniques so participants can monitor forest cover, detect deforestation, and support sustainable forestry planning using satellite imagery.',
            'audience' => 'Forestry officers, environmental scientists, conservation NGOs and land-use planners.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'advanced',
            'tag' => 'high_demand',
            'spine' => 'green',
            'durationWeeks' => 10,
            'price' => 470,
            'originalPrice' => 540,
            'seatsLeft' => 13,
            'nextCohort' => '2026-09-21',
            'mode' => 'hybrid',
            'objectives' => [
                'Understand remote sensing fundamentals and satellite data sources',
                'Detect forest cover change and deforestation over time',
                'Combine GIS and remote sensing for forestry planning',
                'Build monitoring reports for sustainable forestry programmes',
            ],
            'curriculum' => [
                [
                    'title' => 'Remote Sensing Fundamentals',
                    'lessons' => [
                        ['title' => 'How satellite imagery works', 'minutes' => 45],
                        ['title' => 'Sourcing free and commercial imagery', 'minutes' => 40],
                    ],
                ],
                [
                    'title' => 'Forest Monitoring Techniques',
                    'lessons' => [
                        ['title' => 'Classifying forest cover from imagery', 'minutes' => 55],
                        ['title' => 'Detecting deforestation and change', 'minutes' => 50],
                    ],
                ],
                [
                    'title' => 'Planning and Reporting',
                    'lessons' => [
                        ['title' => 'Integrating GIS and remote sensing outputs', 'minutes' => 50],
                        ['title' => 'Building forestry monitoring reports', 'minutes' => 40],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Dennis Kariuki', 'role' => 'GIS and Remote Sensing Specialist'],
        ],
        [
            'slug' => 'gis-for-disaster-risk-management',
            'code' => 'CRS-09',
            'category' => 'monitoring-and-programme-management',
            'title' => 'GIS for Disaster Risk Management',
            'tagline' => 'Map hazards and vulnerability to plan disaster response.',
            'shortDescription' => 'Use GIS to map hazards, vulnerability and exposure, and support faster, better-informed disaster response.',
            'introduction' => 'Effective disaster risk management starts with knowing where hazards, vulnerable populations and critical infrastructure overlap. This seminar introduces GIS techniques for hazard mapping, vulnerability analysis and response planning used by disaster management teams.',
            'audience' => 'Disaster risk management officers, emergency response coordinators and humanitarian programme staff.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'intermediate',
            'tag' => 'high_demand',
            'spine' => 'blue',
            'durationWeeks' => 5,
            'price' => 380,
            'originalPrice' => null,
            'seatsLeft' => 17,
            'nextCohort' => '2026-09-28',
            'mode' => 'online',
            'objectives' => [
                'Map natural and man-made hazards affecting a project area',
                'Assess population and infrastructure vulnerability spatially',
                'Combine hazard and vulnerability layers into risk maps',
                'Use GIS outputs to support disaster response planning',
            ],
            'curriculum' => [
                [
                    'title' => 'Hazard Mapping Foundations',
                    'lessons' => [
                        ['title' => 'GIS concepts for disaster risk', 'minutes' => 40],
                        ['title' => 'Mapping hazard zones', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Vulnerability and Exposure Analysis',
                    'lessons' => [
                        ['title' => 'Assessing population vulnerability', 'minutes' => 50],
                        ['title' => 'Mapping critical infrastructure exposure', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Risk Mapping and Response Planning',
                    'lessons' => [
                        ['title' => 'Building composite risk maps', 'minutes' => 50],
                        ['title' => 'Supporting response planning with GIS', 'minutes' => 35],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Faith Wanjiru', 'role' => 'Public Health GIS Analyst'],
        ],
        [
            'slug' => 'advanced-web-based-mapping-applications-using-open-source-gis-tools',
            'code' => 'CRS-10',
            'category' => 'gis-and-mapping',
            'title' => 'Advanced Web-Based Mapping Applications Using Open Source GIS Tools',
            'tagline' => 'Build interactive web maps with free, open-source tools.',
            'shortDescription' => 'Design and deploy interactive web mapping applications using open-source GIS libraries and platforms.',
            'introduction' => 'Static maps are often not enough for modern programmes that need to share live, interactive spatial data with teams and the public. This advanced course teaches participants to build web-based mapping applications using open-source GIS tools, from data preparation through to a deployed, interactive map.',
            'audience' => 'GIS professionals and developers who already understand GIS fundamentals and want to build interactive web maps.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'advanced',
            'tag' => 'portfolio_track',
            'spine' => 'bright',
            'durationWeeks' => 10,
            'price' => 480,
            'originalPrice' => 550,
            'seatsLeft' => 10,
            'nextCohort' => '2026-09-14',
            'mode' => 'online',
            'objectives' => [
                'Understand the architecture of web-based mapping applications',
                'Prepare and serve spatial data for the web',
                'Build interactive maps using open-source JavaScript libraries',
                'Deploy a working web mapping application',
            ],
            'curriculum' => [
                [
                    'title' => 'Web Mapping Foundations',
                    'lessons' => [
                        ['title' => 'How web mapping applications work', 'minutes' => 45],
                        ['title' => 'Preparing and serving spatial data', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Building Interactive Maps',
                    'lessons' => [
                        ['title' => 'Working with open-source mapping libraries', 'minutes' => 55],
                        ['title' => 'Adding layers, popups and interactivity', 'minutes' => 50],
                    ],
                ],
                [
                    'title' => 'Deployment and Publishing',
                    'lessons' => [
                        ['title' => 'Hosting and deploying a web map', 'minutes' => 50],
                        ['title' => 'Performance and maintenance considerations', 'minutes' => 35],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Dennis Kariuki', 'role' => 'GIS and Remote Sensing Specialist'],
        ],
        [
            'slug' => 'mobile-data-collection-using-ona-and-kobo-toolbox',
            'code' => 'CRS-11',
            'category' => 'data-collection-and-management',
            'title' => 'Mobile Data Collection Using Ona and KoboToolbox',
            'tagline' => 'Design and run digital field surveys with Ona and Kobo.',
            'shortDescription' => 'Design digital survey forms and manage field data collection using Ona and KoboToolbox.',
            'introduction' => 'Paper-based surveys slow down field research and introduce data-entry errors. This workshop teaches participants how to design, deploy and manage digital surveys using Ona and KoboToolbox, two of the most widely used mobile data collection platforms in development work.',
            'audience' => 'M&E officers, field researchers, enumerators and data managers running surveys or assessments.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'beginner_friendly',
            'spine' => 'green',
            'durationWeeks' => 5,
            'price' => 340,
            'originalPrice' => null,
            'seatsLeft' => 24,
            'nextCohort' => '2026-09-07',
            'mode' => 'online',
            'objectives' => [
                'Design digital survey forms with skip logic and validation',
                'Deploy surveys to mobile devices using Ona and KoboToolbox',
                'Manage and monitor incoming field data in real time',
                'Export and clean collected data for analysis',
            ],
            'curriculum' => [
                [
                    'title' => 'Digital Survey Design',
                    'lessons' => [
                        ['title' => 'Form design principles', 'minutes' => 40],
                        ['title' => 'Building forms in XLSForm', 'minutes' => 50],
                    ],
                ],
                [
                    'title' => 'Deploying Surveys with Ona and Kobo',
                    'lessons' => [
                        ['title' => 'Setting up a project in KoboToolbox', 'minutes' => 45],
                        ['title' => 'Deploying and testing on mobile devices', 'minutes' => 40],
                    ],
                ],
                [
                    'title' => 'Managing and Exporting Field Data',
                    'lessons' => [
                        ['title' => 'Monitoring incoming submissions', 'minutes' => 35],
                        ['title' => 'Exporting and cleaning collected data', 'minutes' => 40],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Grace Njeri', 'role' => 'Monitoring, Evaluation and Data Specialist'],
        ],
        [
            'slug' => 'mobile-data-collection-using-odk',
            'code' => 'CRS-12',
            'category' => 'data-collection-and-management',
            'title' => 'Mobile Data Collection Using ODK',
            'tagline' => 'Build robust digital surveys with Open Data Kit.',
            'shortDescription' => 'Learn to design and deploy digital data collection forms using the ODK ecosystem.',
            'introduction' => 'ODK (Open Data Kit) is a foundational open-source toolset for mobile data collection, widely used across research, health and development programmes. This training builds practical skills in designing ODK forms, deploying them to the field, and managing the resulting data.',
            'audience' => 'Field researchers, M&E officers and data collection teams working with the ODK ecosystem.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'beginner_friendly',
            'spine' => 'green',
            'durationWeeks' => 5,
            'price' => 340,
            'originalPrice' => null,
            'seatsLeft' => 22,
            'nextCohort' => '2026-09-14',
            'mode' => 'online',
            'objectives' => [
                'Understand the ODK ecosystem and how its tools fit together',
                'Design ODK forms including skip logic and constraints',
                'Deploy forms to ODK Collect on mobile devices',
                'Manage, export and quality-check collected data',
            ],
            'curriculum' => [
                [
                    'title' => 'Introduction to ODK',
                    'lessons' => [
                        ['title' => 'The ODK ecosystem explained', 'minutes' => 35],
                        ['title' => 'Setting up your first ODK project', 'minutes' => 40],
                    ],
                ],
                [
                    'title' => 'Form Design in ODK',
                    'lessons' => [
                        ['title' => 'Building forms with skip logic', 'minutes' => 50],
                        ['title' => 'Adding validation and constraints', 'minutes' => 40],
                    ],
                ],
                [
                    'title' => 'Field Deployment and Data Management',
                    'lessons' => [
                        ['title' => 'Deploying to ODK Collect', 'minutes' => 40],
                        ['title' => 'Quality-checking and exporting data', 'minutes' => 40],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Grace Njeri', 'role' => 'Monitoring, Evaluation and Data Specialist'],
        ],
        [
            'slug' => 'research-design-data-management-and-statistical-analysis-using-spss',
            'code' => 'CRS-13',
            'category' => 'data-collection-and-management',
            'title' => 'Research Design, Data Management and Statistical Analysis Using SPSS',
            'tagline' => 'From research design to statistical analysis in SPSS.',
            'shortDescription' => 'Cover the full research cycle: designing studies, managing data and running statistical analysis in SPSS.',
            'introduction' => 'Good research depends on sound design as much as sound analysis. This workshop takes participants through the full research cycle, from designing a study and managing collected data through to running and interpreting statistical analysis in SPSS.',
            'audience' => 'Researchers, M&E staff, graduate students and analysts who need practical SPSS and research design skills.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'intermediate',
            'tag' => 'portfolio_track',
            'spine' => 'blue',
            'durationWeeks' => 10,
            'price' => 450,
            'originalPrice' => 500,
            'seatsLeft' => 19,
            'nextCohort' => '2026-09-07',
            'mode' => 'hybrid',
            'objectives' => [
                'Design a research study with an appropriate methodology and sampling approach',
                'Prepare and manage datasets for statistical analysis',
                'Run descriptive and inferential statistics in SPSS',
                'Interpret and report statistical findings clearly',
            ],
            'curriculum' => [
                [
                    'title' => 'Research Design',
                    'lessons' => [
                        ['title' => 'Choosing a research methodology', 'minutes' => 45],
                        ['title' => 'Sampling design and questionnaire planning', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Data Management in SPSS',
                    'lessons' => [
                        ['title' => 'Importing and structuring data in SPSS', 'minutes' => 40],
                        ['title' => 'Data cleaning and recoding', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Statistical Analysis and Reporting',
                    'lessons' => [
                        ['title' => 'Descriptive statistics and cross-tabulation', 'minutes' => 50],
                        ['title' => 'Inferential tests and interpretation', 'minutes' => 55],
                        ['title' => 'Reporting statistical findings', 'minutes' => 35],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Grace Njeri', 'role' => 'Monitoring, Evaluation and Data Specialist'],
        ],
        [
            'slug' => 'advanced-financial-management-grants-management-and-auditing-for-donor-funded-projects',
            'code' => 'CRS-14',
            'category' => 'finance-and-operations',
            'title' => 'Advanced Financial Management, Grants Management & Auditing for Donor Funded Projects',
            'tagline' => 'Manage donor funds with confidence and compliance.',
            'shortDescription' => 'Build advanced skills in financial management, grants compliance and auditing for donor-funded projects.',
            'introduction' => 'Donor-funded projects come with strict financial reporting and compliance requirements. This programme builds advanced skills in financial management, grants administration and audit preparedness so finance and programme teams can manage donor funds with confidence and full compliance.',
            'audience' => 'Finance officers, grants managers, project accountants and programme managers handling donor-funded budgets.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'advanced',
            'tag' => 'career_switch',
            'spine' => 'black',
            'durationWeeks' => 10,
            'price' => 490,
            'originalPrice' => 560,
            'seatsLeft' => 16,
            'nextCohort' => '2026-09-21',
            'mode' => 'hybrid',
            'objectives' => [
                'Apply donor financial management and reporting standards',
                'Manage grant budgets, amendments and compliance requirements',
                'Prepare for and respond to donor and statutory audits',
                'Strengthen internal controls for donor-funded projects',
            ],
            'curriculum' => [
                [
                    'title' => 'Donor Financial Management',
                    'lessons' => [
                        ['title' => 'Donor financial reporting standards', 'minutes' => 45],
                        ['title' => 'Budgeting for donor-funded projects', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Grants Management and Compliance',
                    'lessons' => [
                        ['title' => 'Managing grant budgets and amendments', 'minutes' => 50],
                        ['title' => 'Compliance and eligibility of costs', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Auditing and Internal Controls',
                    'lessons' => [
                        ['title' => 'Preparing for donor and statutory audits', 'minutes' => 50],
                        ['title' => 'Strengthening internal financial controls', 'minutes' => 45],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Samuel Mwangi', 'role' => 'Finance and Grants Management Consultant'],
        ],
        [
            'slug' => 'warehouse-and-store-management',
            'code' => 'CRS-15',
            'category' => 'finance-and-operations',
            'title' => 'Warehouse and Store Management',
            'tagline' => 'Run efficient, accountable warehouses and stores.',
            'shortDescription' => 'Build practical skills in inventory control, warehouse layout and store management for programme operations.',
            'introduction' => 'Effective warehouse and store management keeps programmes running and resources accountable. This workshop covers the practical skills needed to manage inventory, organise warehouse space, and maintain accurate stock records in line with organisational and donor requirements.',
            'audience' => 'Warehouse officers, logistics staff, store keepers and operations teams responsible for inventory and supplies.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'beginner_friendly',
            'spine' => 'green',
            'durationWeeks' => 5,
            'price' => 330,
            'originalPrice' => null,
            'seatsLeft' => 20,
            'nextCohort' => '2026-09-28',
            'mode' => 'in_person',
            'objectives' => [
                'Organise warehouse layout for efficiency and safety',
                'Maintain accurate stock cards and inventory records',
                'Apply stock control methods to reduce loss and wastage',
                'Prepare warehouse operations for internal and donor audits',
            ],
            'curriculum' => [
                [
                    'title' => 'Warehouse Operations Fundamentals',
                    'lessons' => [
                        ['title' => 'Warehouse layout and safety', 'minutes' => 40],
                        ['title' => 'Receiving and dispatching stock', 'minutes' => 40],
                    ],
                ],
                [
                    'title' => 'Inventory and Stock Control',
                    'lessons' => [
                        ['title' => 'Stock cards and inventory records', 'minutes' => 40],
                        ['title' => 'Stock control methods and loss prevention', 'minutes' => 45],
                    ],
                ],
                [
                    'title' => 'Compliance and Reporting',
                    'lessons' => [
                        ['title' => 'Preparing for warehouse audits', 'minutes' => 40],
                        ['title' => 'Reporting stock movements and status', 'minutes' => 35],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Samuel Mwangi', 'role' => 'Finance and Grants Management Consultant'],
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
                    'short_description' => $courseData['shortDescription'],
                    'full_description' => $this->buildFullDescription($courseData),
                    'outline' => ['objectives' => $courseData['objectives']],
                    'thumbnail_url' => '/storage/courses/' . $courseData['image'],
                    'price' => $courseData['price'],
                    'original_price' => $courseData['originalPrice'],
                    'currency' => 'USD',
                    'status' => 'published',
                    'classification' => 'skills_professional',
                    'certificate_kind' => 'completion',
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

    /**
     * Builds the single rich-text `full_description` document (Introduction,
     * Course Objectives, Duration, Target Audience) the instructor-facing
     * RichTextEditor produces, mirroring how a real instructor would author it.
     */
    private function buildFullDescription(array $courseData): string
    {
        $objectives = collect($courseData['objectives'])
            ->map(fn ($item) => "<li>{$item}</li>")
            ->implode('');

        return
            '<h2>Introduction</h2>'
            . "<p>{$courseData['introduction']}</p>"
            . '<h2>Course Objectives</h2>'
            . "<ul>{$objectives}</ul>"
            . '<h2>Duration</h2>'
            . "<p>{$courseData['durationWeeks']} weeks.</p>"
            . '<h2>Target Audience</h2>'
            . "<p>{$courseData['audience']}</p>";
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
            ['id' => $user->id],
            [
                'email' => $user->email,
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
                'approval_status' => 'approved',
            ]
        );

        return $user;
    }

    private function seedModules(Course $course, array $curriculum): void
    {
        foreach ($curriculum as $index => $moduleData) {
            $module = CourseModule::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'order_index' => $index,
                ],
                [
                    'title' => $moduleData['title'],
                ]
            );

            $this->seedLessons($module, $moduleData['lessons']);
        }
    }

    private function seedLessons(CourseModule $module, array $lessons): void
    {
        foreach ($lessons as $index => $lessonData) {
            CourseLesson::updateOrCreate(
                [
                    'module_id' => $module->id,
                    'order_index' => $index,
                ],
                [
                    'title' => $lessonData['title'],
                    'content_type' => 'text',
                    'content_url_or_body' => "<p>Lesson content for \"{$lessonData['title']}\" will be added by the instructor.</p>",
                    'duration_minutes' => $lessonData['minutes'],
                    'is_preview' => $index === 0,
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
        $instructorProfile = InstructorProfile::where('user_id', $instructor->id)->first();

        CourseMentor::updateOrCreate(
            ['course_id' => $course->id],
            [
                'name' => $instructor->name,
                'bio' => $instructorProfile?->bio,
                'assigned_at' => now(),
            ]
        );
    }
}
