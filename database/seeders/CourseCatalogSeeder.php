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
use Illuminate\Support\Facades\Storage;
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
        [
            'slug' => 'cambridge-igcse',
            'name' => 'Cambridge IGCSE',
            'description' => 'Syllabus-aligned Cambridge IGCSE subject courses for secondary school learners.',
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
                        [
                            'title' => 'Introduction to GIS and spatial thinking',
                            'minutes' => 45,
                            'content' => '<h2>What is GIS?</h2><p>A Geographic Information System (GIS) is a framework for capturing, storing, analysing and displaying data that is tied to a location. In agriculture and food security work, that location might be a farmer\'s plot, a market catchment area, or a district affected by drought. Instead of treating data as rows in a spreadsheet, GIS lets you ask "where" questions: where are yields lowest, where is food insecurity concentrated, where should the next intervention go?</p><h2>Spatial thinking</h2><p>Spatial thinking means habitually asking how location, distance and proximity affect the problem in front of you. Two households with identical incomes can face very different food security outcomes depending on distance to a market or exposure to flooding. Programme staff who think spatially start noticing these patterns before the data even confirms them.</p><h2>Vector vs raster data</h2><p><strong>Vector data</strong> represents discrete features as points (a well), lines (a road) or polygons (a farm boundary). <strong>Raster data</strong> represents continuous surfaces as a grid of cells, such as a satellite image or a rainfall surface. Most agricultural GIS work blends both: vector boundaries laid over raster imagery.</p><h2>Why this matters for your work</h2><p>By the end of this module you will be able to look at a food security question and immediately identify what spatial data you would need to answer it, and whether that data is naturally vector or raster in form. That framing carries through every lesson in this course.</p>',
                        ],
                        [
                            'title' => 'Coordinate systems and map projections',
                            'minutes' => 40,
                            'content' => '<h2>Why coordinates matter</h2><p>Every spatial dataset needs a way to translate real-world locations into numbers. A <strong>coordinate reference system (CRS)</strong> defines how that translation happens. Get it wrong, and two perfectly good datasets will simply fail to line up on a map, even though each one is individually correct.</p><h2>Geographic vs projected coordinate systems</h2><p><strong>Geographic coordinate systems</strong> (like WGS84, the standard used by GPS) express locations as latitude and longitude on a curved model of the Earth. <strong>Projected coordinate systems</strong> flatten that curved surface onto a two-dimensional map using a mathematical projection, which introduces some distortion in shape, area, distance or direction, depending on the projection chosen.</p><h2>Choosing a projection for agricultural mapping</h2><p>For area-based work like crop yield or land-use mapping, an equal-area projection (such as an appropriate UTM zone for your region) keeps area measurements accurate, which matters when you are reporting hectares under cultivation or affected by drought.</p><h2>Practical takeaway</h2><p>Before combining any two datasets, always check their CRS. Reprojecting a layer to match your project\'s working CRS should be one of the first steps in any GIS workflow, not an afterthought when the map "looks wrong".</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Spatial Data Collection and Management',
                    'lessons' => [
                        [
                            'title' => 'Sourcing satellite and survey data',
                            'minutes' => 50,
                            'content' => '<h2>Two main sources of spatial data</h2><p>Agricultural GIS projects typically draw on two categories of data: <strong>satellite/remote sensing imagery</strong> (freely available from missions like Sentinel-2 and Landsat) and <strong>ground survey data</strong> collected directly from farmers, extension staff or field teams.</p><h2>Free satellite imagery sources</h2><p>Platforms like the Copernicus Open Access Hub and USGS EarthExplorer provide free Sentinel and Landsat imagery at resolutions useful for crop and land-use monitoring (10-30m per pixel). These are a practical starting point before considering paid, higher-resolution commercial imagery.</p><h2>Ground survey data</h2><p>Satellite imagery tells you what land cover looks like from above, but not always why. Ground survey data, collected via mobile data collection tools, fills in details imagery cannot capture, such as crop variety, ownership, or self-reported food security status. The strongest agricultural GIS analyses combine both.</p><h2>Matching data to your question</h2><p>Before sourcing any data, write down precisely what question you are answering. "Where is maize grown" is a land-cover classification question best answered by imagery. "Which households are food insecure" needs survey data. Most real questions need both, joined spatially.</p>',
                        ],
                        [
                            'title' => 'Cleaning and structuring spatial datasets',
                            'minutes' => 45,
                            'content' => '<h2>Why spatial data needs cleaning</h2><p>Raw field-collected or downloaded spatial data is rarely analysis-ready. Common problems include missing or malformed coordinates, duplicate records, inconsistent attribute naming, and geometries that do not match their described location (a "farm boundary" polygon that is actually a single point, for example).</p><h2>A practical cleaning checklist</h2><p>1) Check the CRS is correct and consistent across all layers. 2) Remove or flag records with null or clearly invalid coordinates (0,0 is a common sign of a GPS error). 3) Standardise attribute field names and categories, e.g. make sure "maize", "Maize" and "MAIZE" are not treated as three different crops. 4) Check geometry validity, since self-intersecting polygons will break many analysis tools.</p><h2>Structuring for reuse</h2><p>Organise your project into a consistent folder and naming structure, and keep a simple metadata note for each layer recording its source, date, CRS and who prepared it. Six months later, when someone else opens your project, this is the difference between a dataset that is trustworthy and one that has to be re-verified from scratch.</p><h2>Outcome</h2><p>A clean, well-documented dataset is what makes every later analysis step reliable — and it is where most real project time is actually spent, not in the final map production.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Mapping Food Security and Land Use',
                    'lessons' => [
                        [
                            'title' => 'Building crop and land-use maps in QGIS',
                            'minutes' => 55,
                            'content' => '<h2>QGIS as your working tool</h2><p>QGIS is a free, open-source GIS application widely used across development and government agencies. This lesson walks through building a basic crop/land-use map: loading a satellite image, digitising or importing field boundaries, and classifying land cover into categories such as cropland, forest, water and built-up area.</p><h2>Supervised classification, simplified</h2><p>A practical approach for beginners is a simple supervised classification: you select sample areas of known land cover ("training samples") directly on the image, and use QGIS\'s classification tools to extend those labels across the full image based on spectral similarity.</p><h2>Symbolising your map</h2><p>Once classified, use a categorised symbology so each land-use type has a distinct, intuitive colour (green for cropland, blue for water, etc.). Consistent colour conventions make maps far easier for non-GIS colleagues to interpret at a glance.</p><h2>Validating the result</h2><p>Always spot-check your classified map against known ground truth, whether that is your own local knowledge or a sample of survey records. A land-use map that has not been validated against reality is a hypothesis, not a finding.</p>',
                        ],
                        [
                            'title' => 'Vulnerability and food-security mapping',
                            'minutes' => 50,
                            'content' => '<h2>From land use to vulnerability</h2><p>A land-use map tells you what is on the ground; a vulnerability map tells you who is at risk and how badly. Food-security vulnerability mapping typically combines several indicator layers, such as crop yield estimates, market access (distance/travel time), rainfall anomalies and household survey indicators like the Food Consumption Score.</p><h2>Building a composite vulnerability index</h2><p>A common approach is to normalise each indicator layer to a common scale (e.g. 0-1), assign weights based on programme priorities or established frameworks (such as the IPC), and combine them into a single composite score per administrative unit or grid cell.</p><h2>Choosing the right unit of analysis</h2><p>Decide early whether you are mapping at household, village or district level. Aggregating too coarsely (district-level) can hide severe pockets of vulnerability; working too finely without enough underlying data can produce misleadingly precise-looking results.</p><h2>Communicating uncertainty</h2><p>Vulnerability maps are estimates, not certainties. Wherever possible, indicate data recency and confidence, so decision-makers do not treat a vulnerability map as more precise than the underlying data actually supports.</p>',
                        ],
                        [
                            'title' => 'Presenting spatial results to stakeholders',
                            'minutes' => 35,
                            'content' => '<h2>Maps are a communication tool first</h2><p>A technically correct map that a programme manager cannot read in ten seconds has failed at its job. This lesson focuses on turning analysis outputs into maps and short briefs that non-GIS audiences can act on.</p><h2>Core design principles</h2><p>Keep a clear title, a legend that uses plain language (not just class codes), a scale bar and north arrow, and a colour scheme that matches audience expectations (e.g. red/orange for higher severity, not an arbitrary rainbow). Remove any layers or clutter that do not serve the map\'s single main message.</p><h2>One map, one message</h2><p>Resist the urge to show everything on one map. If you have both a land-use map and a vulnerability map, present them as two focused maps rather than one crowded composite, unless the overlay itself is the specific point being made.</p><h2>Pairing maps with a short narrative</h2><p>Accompany every map with two or three sentences explaining what it shows, why it matters, and what action it suggests. Stakeholders remember the sentence long after they forget the map.</p>',
                        ],
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
                        [
                            'title' => 'GIS concepts for environmental monitoring',
                            'minutes' => 45,
                            'content' => '<h2>Why environmental monitoring is a spatial problem</h2><p>Forests, water catchments and degraded land do not respect administrative boundaries, and they change continuously. GIS gives environmental teams a way to track these changes over time and space in a consistent, repeatable way, rather than relying on periodic, subjective field visits alone.</p><h2>Key GIS concepts you will use</h2><p><strong>Layers</strong>: each theme (forest boundary, river network, land cover) is its own layer that can be turned on/off and analysed independently or together. <strong>Attributes</strong>: the non-spatial data attached to each feature (a forest polygon might have attributes for species composition, protection status, and last survey date). <strong>Topology</strong>: the spatial relationships between features, such as whether a proposed logging concession overlaps a protected area boundary.</p><h2>Time as a dimension</h2><p>Unlike a single snapshot map, environmental monitoring almost always requires comparing the same area across multiple time periods. Get in the habit of dating every layer clearly (e.g. "forest_cover_2020", "forest_cover_2024") so change-over-time analysis is straightforward later.</p><h2>Setting up for this course</h2><p>Through this course you will build a small monitoring workflow: source imagery, delineate key features, detect change, and produce planning-ready maps. This first lesson sets the conceptual foundation the rest of the course builds on.</p>',
                        ],
                        [
                            'title' => 'Working with satellite imagery basics',
                            'minutes' => 40,
                            'content' => '<h2>What satellite imagery gives you</h2><p>Satellite sensors capture reflected light across different wavelength bands (visible, near-infrared, and beyond). Combining bands in different ways reveals information invisible to the naked eye, most importantly vegetation health and extent, which is why remote sensing is central to natural resource monitoring.</p><h2>NDVI: a core vegetation index</h2><p>The Normalized Difference Vegetation Index (NDVI) compares near-infrared and red light reflectance to produce a simple, widely used measure of vegetation greenness and density. Healthy, dense vegetation produces high NDVI values; bare soil or water produces low or negative values. NDVI is often the first analysis run on any new imagery for forestry or land-cover work.</p><h2>Choosing the right imagery</h2><p>Match imagery resolution and revisit frequency to your question. Sentinel-2 (10m resolution, ~5-day revisit) is well suited to catchment or landscape-scale forest monitoring; higher-resolution commercial imagery may be justified for small, high-value conservation areas but comes at a cost.</p><h2>Practical workflow</h2><p>Download imagery for your area of interest, clip it to your boundary, and calculate NDVI as a first diagnostic step before any more advanced classification or change detection.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Monitoring Land, Forest and Water Resources',
                    'lessons' => [
                        [
                            'title' => 'Mapping forest cover change',
                            'minutes' => 50,
                            'content' => '<h2>The change-detection approach</h2><p>Forest cover change mapping compares classified land-cover layers from two or more time periods to identify where forest was lost (or, less commonly, gained). The reliability of the result depends heavily on using consistent classification methods and comparable imagery (similar season, similar resolution) across both dates.</p><h2>Step by step</h2><p>1) Classify forest vs non-forest for your baseline year. 2) Repeat the same classification method for your comparison year. 3) Use a change-detection or raster-overlay tool to identify pixels that shifted from forest to non-forest (loss) or the reverse (gain). 4) Convert the change layer into summary statistics, such as hectares lost per district per year.</p><h2>Common pitfalls</h2><p>Seasonal differences between the two images (wet vs dry season) can create false "change" that is really just seasonal variation in vegetation, not actual deforestation. Where possible, compare imagery from the same season each year.</p><h2>From map to message</h2><p>A forest-loss map is most useful for programme decisions when paired with a simple summary: total area lost, the rate of loss over time, and which specific zones are losing forest fastest — these are the numbers programme managers and donors actually need.</p>',
                        ],
                        [
                            'title' => 'Delineating water catchments',
                            'minutes' => 45,
                            'content' => '<h2>What a catchment is, spatially</h2><p>A water catchment (or watershed) is the entire land area that drains to a single outlet point, such as a river or reservoir. Understanding catchment boundaries is essential for water resource management, since actions anywhere upstream in a catchment can affect water quality and quantity downstream.</p><h2>Deriving catchments from elevation data</h2><p>Catchment boundaries are typically delineated using a Digital Elevation Model (DEM), a raster layer where each cell records ground elevation. GIS hydrology tools use the DEM to model flow direction and flow accumulation, from which catchment boundaries for any chosen outlet point can be automatically derived.</p><h2>Free elevation data sources</h2><p>SRTM (Shuttle Radar Topography Mission) data, freely available at 30m resolution globally, is a common and adequate starting point for catchment delineation at programme scale.</p><h2>Using catchments in your analysis</h2><p>Once delineated, a catchment boundary becomes a meaningful unit for aggregating other data. Rather than reporting deforestation or land-use change by arbitrary administrative boundaries, reporting it by catchment often better reflects the actual hydrological consequences of land-use decisions.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Analysis and Conservation Planning',
                    'lessons' => [
                        [
                            'title' => 'Detecting land degradation over time',
                            'minutes' => 50,
                            'content' => '<h2>Defining degradation spatially</h2><p>Land degradation shows up in GIS data as a sustained decline in vegetation productivity (falling NDVI trends over multiple years), increased bare soil exposure, or soil erosion indicators, rather than a single before/after snapshot. Distinguishing genuine long-term degradation from short-term drought effects requires looking at a multi-year time series, not just two dates.</p><h2>Building a simple trend analysis</h2><p>Using a multi-year stack of NDVI images, you can calculate a per-pixel trend: is greenness increasing, stable, or declining over the period? Consistently declining areas, especially where the decline does not track with a single bad rainfall year, are strong degradation candidates for field verification.</p><h2>Combining with other evidence</h2><p>Strengthen a degradation assessment by layering in rainfall data (to rule out drought as the sole cause), land-use history (recent conversion to agriculture is a common driver) and slope (steeper land degrades faster under poor management).</p><h2>From detection to planning</h2><p>The output of this analysis is not just a map of "bad" areas — it is a prioritised list of locations where restoration or intervention would have the greatest impact, which feeds directly into the conservation planning covered next.</p>',
                        ],
                        [
                            'title' => 'Building maps for conservation planning',
                            'minutes' => 45,
                            'content' => '<h2>From diagnosis to decision-support</h2><p>Conservation planning maps combine everything built earlier in this course, forest cover, catchments, and degradation trends, into a single decision-support product that helps prioritise where conservation resources should go.</p><h2>Multi-criteria prioritisation</h2><p>A common approach is a simple weighted overlay: rank each candidate area on a small set of criteria (ecological value, degree of threat, feasibility of intervention, community support) and combine the scores into a priority ranking. This need not be complex to be useful — a transparent, explainable scoring approach is often more trusted by stakeholders than a highly technical model they cannot interrogate.</p><h2>Designing the final planning map</h2><p>The final map should clearly show priority zones using an intuitive colour ramp, include the key input layers as reference, and be accompanied by a short rationale explaining how priorities were determined, so reviewers can question and refine the logic rather than just accept the output.</p><h2>Closing the loop</h2><p>Good conservation planning maps are living documents. Build your workflow so that when new imagery or field data becomes available, the whole prioritisation can be re-run rather than redone from scratch.</p>',
                        ],
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
                        [
                            'title' => 'Spatial epidemiology basics',
                            'minutes' => 45,
                            'content' => '<h2>Why disease has a geography</h2><p>Disease transmission, healthcare access and health outcomes are all shaped by location: proximity to water sources, population density, travel routes and distance to health facilities all influence where and how disease spreads. Spatial epidemiology is the discipline of studying these patterns using location-tagged health data.</p><h2>Foundational concepts</h2><p><strong>Case mapping</strong> plots individual disease cases as points, revealing clustering that a table of case counts by district would hide. <strong>Rate mapping</strong> normalises case counts by population, since raw case counts alone can mislead — a dense urban area will always have more absolute cases than a sparse rural one, even at a lower rate.</p><h2>The modifiable areal unit problem</h2><p>How you aggregate data (by village, district, or region) can change the apparent pattern of disease, sometimes dramatically. Always be explicit about the unit of analysis used, and consider checking whether your conclusions hold at more than one level of aggregation.</p><h2>Ethics and privacy</h2><p>Precise case-location mapping can risk identifying individuals, particularly in small communities. This course emphasises appropriate aggregation and anonymisation practices throughout, which we will revisit in every mapping lesson.</p>',
                        ],
                        [
                            'title' => 'Sourcing and structuring health data',
                            'minutes' => 40,
                            'content' => '<h2>Where health surveillance data comes from</h2><p>Typical sources include facility-based reporting systems (like DHIS2), field surveillance teams, laboratory confirmation records, and census or population estimates used as denominators for rate calculations.</p><h2>Joining health data to geography</h2><p>Health records are rarely collected with GPS coordinates attached. More commonly, you will need to join records to geography using a shared administrative code or facility name, which means consistent naming and coding conventions across your health data and your spatial boundary files are essential.</p><h2>Handling data quality issues</h2><p>Surveillance data is often incomplete or delayed, especially during active outbreaks. Structure your workflow to clearly flag data recency and completeness on any output map, rather than presenting partial data with the same confidence as complete data.</p><h2>Building your working dataset</h2><p>By the end of this lesson you should have a clean table of case or facility records, each correctly linked to a spatial unit (facility point, village, or district polygon), ready for the mapping work in the next module.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Mapping Outbreaks and Coverage',
                    'lessons' => [
                        [
                            'title' => 'Mapping disease incidence and clusters',
                            'minutes' => 55,
                            'content' => '<h2>From case list to cluster map</h2><p>This lesson covers two complementary techniques: <strong>choropleth mapping</strong>, which shades administrative units by case rate, and <strong>point-pattern/cluster analysis</strong>, which identifies statistically significant clustering of individual cases beyond what random chance would produce.</p><h2>Choosing classification breaks</h2><p>How you bin case rates into colour categories materially changes how "severe" an area looks on the map. Prefer standard, defensible classification methods (such as natural breaks or quantiles) over manually chosen thresholds, and always disclose which method you used.</p><h2>Identifying genuine clusters</h2><p>Basic hotspot statistics (such as Getis-Ord Gi*) help distinguish a true spatial cluster of cases from cases that merely appear close together because that is where the population is concentrated. This distinction matters directly for where a response team should focus limited resources.</p><h2>Presenting outbreak maps responsibly</h2><p>During an active outbreak, maps can shape real-time decisions and public perception. Keep symbology proportionate (avoid alarmist colour choices for genuinely low case counts) and always date-stamp the map clearly.</p>',
                        ],
                        [
                            'title' => 'Health facility coverage analysis',
                            'minutes' => 45,
                            'content' => '<h2>What coverage analysis answers</h2><p>Coverage analysis asks: how much of the population can reasonably reach a health facility, and where are the gaps? This directly supports decisions about where new facilities, mobile clinics or outreach services are most needed.</p><h2>Service area methods</h2><p>A simple approach uses straight-line buffer distances (e.g. a 5km radius) around each facility. A more realistic approach uses network or travel-time analysis, accounting for actual roads, terrain and transport options, since straight-line distance can be very misleading in areas with poor road networks or physical barriers like rivers.</p><h2>Overlaying population data</h2><p>Combine your service-area layer with population distribution data (such as WorldPop) to estimate how many people fall inside versus outside effective coverage, broken down by area if useful for programme targeting.</p><h2>Turning gaps into recommendations</h2><p>The most useful output of a coverage analysis is not the map alone but a ranked list of underserved areas, ideally cross-checked against disease burden from the previous lesson, so response planning addresses both need and access together.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Surveillance Dashboards and Reporting',
                    'lessons' => [
                        [
                            'title' => 'Building a surveillance dashboard',
                            'minutes' => 50,
                            'content' => '<h2>Why a dashboard, not just a map</h2><p>Active disease surveillance needs recurring, not one-off, spatial monitoring. A dashboard combines maps with key summary indicators (new cases this week, cumulative cases, facility reporting completeness) in a single view that a response team can check regularly without redoing analysis each time.</p><h2>Core dashboard components</h2><p>A minimal but effective surveillance dashboard includes: a current case-distribution map, a trend chart of cases over time, a facility reporting-completeness indicator, and clear "last updated" timestamps so users know how current the data is.</p><h2>Designing for the audience</h2><p>Surveillance dashboards are often viewed under time pressure during an active response. Prioritise clarity over completeness: the three or four numbers and one map that matter most, not every metric you are capable of producing.</p><h2>Keeping it maintainable</h2><p>Build your dashboard around a repeatable data-refresh process from day one. A dashboard that requires hours of manual rebuilding each week will quietly stop being updated once the initial excitement fades.</p>',
                        ],
                        [
                            'title' => 'Communicating findings to response teams',
                            'minutes' => 35,
                            'content' => '<h2>The gap between analysis and action</h2><p>A technically excellent surveillance map does nothing if response teams cannot quickly extract what it means for their next decision. This lesson focuses on the final, often under-taught step: translating spatial findings into a clear, actionable briefing.</p><h2>Structuring a spatial briefing</h2><p>A strong briefing states the key finding first ("Cases are clustering in three wards in the northeast"), follows with the supporting map, and ends with a specific recommended action or question for the response team, not just "for information."</p><h2>Anticipating questions</h2><p>Response teams will typically ask: how confident are we in this pattern, how has it changed since the last update, and what would we expect to see next if the pattern continues. Prepare answers to these before presenting, not just the map itself.</p><h2>Closing the loop</h2><p>After any briefing, note what decision was actually made as a result. Over time, this record helps you understand which types of surveillance maps genuinely drive action, and which are being produced but not used.</p>',
                        ],
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
                        [
                            'title' => 'Planning a spatial data collection exercise',
                            'minutes' => 45,
                            'content' => '<h2>Plan before you collect</h2><p>The single biggest driver of poor-quality spatial data is starting collection before the plan is settled. Before anyone goes to the field, you need clarity on: what features are you collecting (points, lines, or polygons?), what attributes matter for each, what coordinate system and accuracy level you need, and who owns quality control.</p><h2>Defining your data schema</h2><p>Write out a simple data dictionary: each field name, its type (text, number, date, category list), and any validation rules, before building any form. Changing the schema mid-collection is far more costly than getting a few extra minutes of planning right upfront.</p><h2>Sampling and coverage strategy</h2><p>Decide whether you need complete enumeration (every farm, every facility) or a representative sample, and document the logic. This decision drives your budget, timeline and the statistical claims you can later make from the data.</p><h2>Piloting</h2><p>Always pilot your data collection plan with a small team over a day or two before full rollout. Piloting reliably surfaces schema gaps, confusing field instructions and unrealistic time estimates while the cost of fixing them is still low.</p>',
                        ],
                        [
                            'title' => 'Collecting GPS and attribute data in the field',
                            'minutes' => 50,
                            'content' => '<h2>GPS accuracy basics</h2><p>Consumer-grade GPS (including smartphones) typically has 3-10m accuracy under open sky, worse under tree canopy or near tall buildings. Know your accuracy requirement before choosing equipment: mapping district boundaries tolerates far more error than mapping individual water points for engineering purposes.</p><h2>Capturing points, lines and polygons</h2><p>Point features (a well, a facility) are captured as a single coordinate. Line features (a road, a river segment) are captured by walking or driving the feature while recording a track. Polygon features (a field boundary, a catchment) are captured by walking the perimeter or, increasingly, by tracing on top of satellite imagery when ground access is hard.</p><h2>Attribute data discipline</h2><p>Every spatial feature should be captured together with its attributes at the same time, not linked up later from separate notes — that gap is where most field data errors and lost records happen.</p><h2>Field quality checks</h2><p>Build in simple checks the field team can do on the spot: does the captured point actually fall within the expected area on a basemap, are required fields all filled. Catching an error in the field costs minutes; catching it back at the office can cost a whole re-visit.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Data Management and Analysis',
                    'lessons' => [
                        [
                            'title' => 'Organising GIS datasets and metadata',
                            'minutes' => 40,
                            'content' => '<h2>Why organisation is a skill, not an afterthought</h2><p>A GIS project accumulates dozens of layers quickly: raw field data, cleaned versions, derived analysis outputs, reference boundaries. Without a consistent structure, projects become unmanageable within weeks, and work has to be redone because no one can tell which version of a file is current.</p><h2>A practical folder and naming convention</h2><p>Separate raw/source data from processed/derived data in different folders, and never edit raw source files in place. Use consistent, descriptive file names that include theme and date (e.g. "landcover_2024_v2.shp"), avoiding vague names like "final_final_v3".</p><h2>Metadata: the story behind the data</h2><p>For every dataset, record at minimum: source, collection or download date, CRS, and any processing already applied. This is what lets someone else — or you, six months later — trust and correctly reuse a layer instead of starting over.</p><h2>Version control for spatial data</h2><p>Even a simple discipline of appending a version number and a short changelog note each time a dataset is significantly edited prevents the single most common GIS project failure: analysis built on a dataset that was silently superseded.</p>',
                        ],
                        [
                            'title' => 'Core spatial analysis techniques',
                            'minutes' => 55,
                            'content' => '<h2>The analysis toolkit</h2><p>This lesson covers the small set of spatial operations that underlie the vast majority of real GIS analysis: <strong>buffering</strong> (creating a zone of a given distance around a feature), <strong>overlay/intersection</strong> (combining two layers to find where they coincide), and <strong>proximity analysis</strong> (measuring distance from features to a reference point or layer).</p><h2>Buffering in practice</h2><p>Buffering answers "what is within X distance of this feature" — for example, which villages fall within 5km of a water source. Buffer distance should be chosen based on a real programme rationale, not an arbitrary round number.</p><h2>Overlay analysis</h2><p>Overlay lets you answer compound questions such as "which agricultural land also falls within a flood-risk zone." The result is a new layer combining attributes from both inputs, which is often the step that turns two separate datasets into a genuinely new insight.</p><h2>Choosing the right technique</h2><p>Before running any analysis, restate your question in plain language and identify which of these core techniques (or combination of them) actually answers it. It is easy to produce a technically correct analysis that does not actually address the original question.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Visualization and Mapping',
                    'lessons' => [
                        [
                            'title' => 'Cartographic design principles',
                            'minutes' => 45,
                            'content' => '<h2>Cartography is applied communication</h2><p>Good cartographic design is not decoration — it directly determines whether a map is understood correctly or misread. This lesson covers the core principles: visual hierarchy, colour theory, and generalisation.</p><h2>Visual hierarchy</h2><p>The most important information on your map should be the most visually prominent. Use size, colour saturation and contrast deliberately to guide the eye to what matters most first, with reference/background layers rendered more subtly.</p><h2>Colour choices that hold up</h2><p>Use sequential colour schemes (light to dark) for data that goes from low to high, diverging schemes for data with a meaningful midpoint, and avoid red-green combinations that are indistinguishable to colour-blind viewers. Free, tested palettes (such as ColorBrewer) remove most of the guesswork.</p><h2>Generalisation and clutter</h2><p>Not every detail needs to appear at every zoom level. Simplify geometries and reduce label density as appropriate, since a map crowded with every available label is often less informative than one showing only what is relevant to its purpose.</p>',
                        ],
                        [
                            'title' => 'Publishing maps and interactive dashboards',
                            'minutes' => 50,
                            'content' => '<h2>From project file to shareable output</h2><p>A map that only exists inside your GIS software has limited reach. This lesson covers exporting to shareable formats (static PDF/image exports for reports) and publishing to interactive platforms for wider audiences.</p><h2>Static exports done well</h2><p>When exporting for a report or presentation, set an appropriate resolution (300 DPI for print), and always double-check the map still includes its title, legend, scale bar and source note at the export size — elements can get clipped or shrink to illegibility if not checked at export time.</p><h2>Interactive dashboards</h2><p>Web-based dashboard tools let stakeholders explore data themselves: turning layers on/off, clicking features for details, filtering by category or date. This is especially valuable for ongoing monitoring projects where the underlying data updates regularly.</p><h2>Maintaining published outputs</h2><p>Before publishing anything, plan how it will be kept current. A dashboard that shows data from eighteen months ago, with no update date visible, actively misleads users who assume it is live — always display a clear "last updated" indicator.</p>',
                        ],
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
                        [
                            'title' => 'GIS concepts for crime analysis',
                            'minutes' => 40,
                            'content' => '<h2>Crime as spatial behaviour</h2><p>Crime is not randomly distributed across space. It concentrates around opportunity, routine activity patterns and environmental design, which is precisely why spatial analysis is one of the most effective tools available to crime analysts.</p><h2>Core theoretical grounding</h2><p><strong>Routine activity theory</strong> holds that crime occurs where a motivated offender, a suitable target and the absence of a capable guardian converge in space and time. <strong>Crime pattern theory</strong> observes that offenders tend to commit crimes within their own routine "activity space" rather than randomly across a city. Both theories are why crime mapping — not just crime counting — reveals actionable patterns.</p><h2>What GIS adds to crime analysis</h2><p>GIS lets analysts move beyond simple crime-type tallies to ask spatial questions: which specific locations repeatedly generate incidents, how patterns shift by time of day or season, and where different crime types overlap geographically, all of which inform where resources are most effectively deployed.</p><h2>Setting the stage</h2><p>This course builds a complete workflow: preparing incident data, identifying hotspots and trends, and presenting findings that lead to concrete resourcing decisions.</p>',
                        ],
                        [
                            'title' => 'Preparing incident data for mapping',
                            'minutes' => 45,
                            'content' => '<h2>Where incident data comes from</h2><p>Crime incident data typically originates from police report records, which almost always include a location field of some kind (an address, an intersection, or coordinates) alongside incident type, date and time.</p><h2>Geocoding: turning addresses into points</h2><p>Most incident records need to be geocoded, converted from a text address into map coordinates, before they can be mapped. Geocoding accuracy varies: expect some records to fail to match or match only approximately, and always report your match rate so downstream analysis accounts for this gap.</p><h2>Data sensitivity and aggregation</h2><p>Individual incident locations can be sensitive, particularly for crimes involving victims. Establish clear rules early for when analysis and outputs should use aggregated units (e.g. block or ward level) rather than exact points, balancing analytical precision against privacy and safety considerations.</p><h2>Standardising crime categories</h2><p>Ensure incident-type categories are consistent before analysis; inconsistent or overly granular categories (dozens of near-duplicate labels) will fragment your data and understate the true concentration of any given crime type.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Hotspot and Trend Analysis',
                    'lessons' => [
                        [
                            'title' => 'Identifying crime hotspots',
                            'minutes' => 50,
                            'content' => '<h2>What makes a hotspot statistically real</h2><p>A cluster of points on a map can look like a hotspot purely by chance, especially in areas with naturally higher population or foot traffic. Formal hotspot analysis methods, such as Kernel Density Estimation and Getis-Ord Gi*, test whether a cluster is statistically significant, not just visually apparent.</p><h2>Kernel Density Estimation (KDE)</h2><p>KDE produces a smooth, continuous density surface from discrete incident points, which is often the most intuitive way to present concentration to non-technical audiences — a heatmap-style output most people can interpret immediately.</p><h2>Choosing analysis parameters carefully</h2><p>Both the search radius (for KDE) and the spatial unit of analysis materially change what looks like a hotspot. Document and justify your parameter choices, and where practical, test whether findings hold under slightly different parameter settings.</p><h2>From hotspot to explanation</h2><p>Identifying a hotspot is the start of analysis, not the end. Effective crime analysts follow up by asking why a location concentrates incidents (poor lighting, a specific venue, a transit hub) since that explanation is what actually informs an effective response.</p>',
                        ],
                        [
                            'title' => 'Analysing trends over time',
                            'minutes' => 45,
                            'content' => '<h2>Space and time together</h2><p>A location that was a hotspot last year may not be this year, and vice versa. Space-time analysis tracks how crime patterns shift over time, which is essential for evaluating whether interventions are actually working or a hotspot has simply moved.</p><h2>Common temporal patterns</h2><p>Look for seasonal patterns (certain crime types spike in particular months), day-of-week and time-of-day patterns (which inform patrol scheduling), and longer-term trend direction (is a hotspot severity increasing, stable or declining over multiple periods).</p><h2>Before/after intervention analysis</h2><p>When evaluating a targeted intervention (increased patrols, environmental changes), compare incident patterns in the target area before and after, alongside a comparable control area that did not receive the intervention, to help separate genuine impact from citywide trends unrelated to the intervention.</p><h2>Avoiding misleading trend claims</h2><p>Short time windows and small incident counts can produce dramatic-looking percentage changes that are actually just statistical noise. Always consider whether your sample size and time window are sufficient to support the trend claim being made.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Reporting and Decision Support',
                    'lessons' => [
                        [
                            'title' => 'Spatial statistics for resource planning',
                            'minutes' => 45,
                            'content' => '<h2>From analysis to resource allocation</h2><p>The practical purpose of crime mapping is usually to inform where limited resources, patrol time, community programmes, infrastructure investment, should be directed. This lesson connects the hotspot and trend techniques already covered to concrete planning decisions.</p><h2>Prioritisation frameworks</h2><p>A simple, defensible approach combines incident density, trend direction and severity (crime type weighting) into a single priority score per area, similar in spirit to the vulnerability-index approach used in other spatial planning contexts.</p><h2>Balancing efficiency and equity</h2><p>Purely density-driven resource allocation can create feedback loops, more patrols in an area increase detected incidents there, reinforcing the allocation. Be explicit about this risk when presenting resourcing recommendations, and consider incorporating community and equity considerations alongside pure incident density.</p><h2>Monitoring allocation outcomes</h2><p>Once resources are reallocated based on spatial analysis, track outcomes over the following months using the same trend methods from the previous lesson, so the resourcing model itself can be refined based on evidence.</p>',
                        ],
                        [
                            'title' => 'Presenting findings to stakeholders',
                            'minutes' => 35,
                            'content' => '<h2>Different audiences, different maps</h2><p>Crime analysis findings often need to reach very different audiences: operational commanders who need actionable, current detail, and community or oversight bodies who need context and trend framing, not raw incident locations.</p><h2>What operational audiences need</h2><p>Commanders generally want current-period hotspot maps with clear priority ranking and enough detail to deploy resources immediately, refreshed on a predictable schedule they can rely on.</p><h2>What community and oversight audiences need</h2><p>Community-facing presentations should emphasise trends and context over granular incident mapping, and should be reviewed carefully for privacy and framing, since crime maps shared publicly can affect neighbourhood perception and property values.</p><h2>The core communication discipline</h2><p>Regardless of audience, lead with the finding and its implication, not the methodology. Save discussion of KDE parameters or classification methods for technical appendices or follow-up questions, not the headline of the briefing.</p>',
                        ],
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
                        [
                            'title' => 'ArcGIS interface and navigation',
                            'minutes' => 40,
                            'content' => '<h2>Orienting yourself in ArcGIS</h2><p>ArcGIS Desktop (via ArcGIS Pro) organises work around a <strong>project</strong>, which contains maps, layouts, and connections to your data. Understanding this project structure from the start avoids a lot of confusion later about where things "live".</p><h2>Key interface areas</h2><p>The <strong>Contents pane</strong> lists every layer in your current map and controls draw order and visibility. The <strong>Map view</strong> is your main working canvas. The <strong>Catalog pane</strong> is your file browser for the project, showing all connected data sources, geodatabases and toolboxes. The <strong>Geoprocessing pane</strong> is where you search for and run analysis tools.</p><h2>Ribbon-based workflow</h2><p>Like modern Office applications, ArcGIS Pro uses a ribbon interface organised by task (Map, Insert, Analysis, View). Spend time in this first lesson simply exploring each ribbon tab so later lessons make sense in context rather than feeling like memorised steps.</p><h2>Setting up your first project</h2><p>Create a new project, connect it to a folder of practice data, and add a layer to the map. This simple sequence, connect, add, view, is the pattern you will repeat constantly throughout the rest of this course.</p>',
                        ],
                        [
                            'title' => 'Loading and organising spatial data',
                            'minutes' => 45,
                            'content' => '<h2>Supported data formats</h2><p>ArcGIS works with several spatial data formats: shapefiles (the most common legacy vector format), file geodatabases (Esri\'s preferred modern format, offering better performance and data integrity), and various raster formats for imagery.</p><h2>Why geodatabases over shapefiles</h2><p>File geodatabases handle larger datasets more efficiently, support more field types, and avoid the shapefile\'s awkward multi-file structure (a single shapefile "layer" is actually 3-8 separate files that must all stay together). For any new project, prefer creating data in a geodatabase.</p><h2>Connecting to data</h2><p>Use Catalog pane folder and geodatabase connections to browse to your data, rather than hunting through your operating system\'s file explorer separately — this keeps your data sources properly registered within the project.</p><h2>Organising layers in the Contents pane</h2><p>Group related layers, rename them with clear, consistent labels rather than default file names, and set an initial draw order (polygons below lines below points) so your map reads correctly from the moment you add data.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Editing and Managing Data',
                    'lessons' => [
                        [
                            'title' => 'Creating and editing feature classes',
                            'minutes' => 50,
                            'content' => '<h2>What a feature class is</h2><p>A feature class is a collection of spatial features sharing the same geometry type (all points, all lines, or all polygons) and the same attribute schema, stored inside a geodatabase. Creating a new feature class means defining both its geometry type and its field structure upfront.</p><h2>The editing workflow</h2><p>ArcGIS editing follows a consistent pattern: start an edit session, use the appropriate construction tool for your geometry type, digitise the feature, populate its attributes, and save your edits. Getting comfortable with this cycle is the core skill of this lesson.</p><h2>Digitising accurately</h2><p>Use snapping (which locks your cursor to existing vertices, edges or endpoints) when digitising features that should connect precisely to others, such as adjacent parcel boundaries or a road network that should be topologically connected.</p><h2>Editing existing features</h2><p>Beyond creating new features, you will often need to reshape, split or merge existing ones as source data or ground conditions change. Practice each of these operations on sample data before working with any production dataset.</p>',
                        ],
                        [
                            'title' => 'Working with attribute tables',
                            'minutes' => 40,
                            'content' => '<h2>The attribute table as a spreadsheet view</h2><p>Every feature class has an associated attribute table, one row per feature, one column per attribute field. You can open, sort, filter and edit this table much like a spreadsheet, while it stays linked to the spatial features on the map.</p><h2>Field types and why they matter</h2><p>Choosing the correct field type (text, short integer, long integer, double, date) when creating a field affects both storage efficiency and what operations are later possible (you cannot do numeric analysis on a number stored as text, for instance).</p><h2>Selecting and querying</h2><p>Use attribute queries (Select By Attributes) to isolate features meeting specific criteria, such as all facilities of a certain type. Selections made in the table highlight the corresponding features on the map, and vice versa, this two-way link is one of the most useful everyday features in ArcGIS.</p><h2>Joins and relates</h2><p>Attribute tables can be joined to external tables (such as a spreadsheet of survey results) using a shared key field, letting you bring in additional data without having to redigitise any geometry.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Analysis and Map Production',
                    'lessons' => [
                        [
                            'title' => 'Basic geoprocessing tools',
                            'minutes' => 55,
                            'content' => '<h2>What geoprocessing means</h2><p>Geoprocessing tools take one or more input datasets, perform an operation, and produce a new output dataset. ArcGIS ships with hundreds of these tools, but a handful cover the majority of everyday analysis needs.</p><h2>Essential tools to know</h2><p><strong>Buffer</strong> creates a zone at a specified distance around input features. <strong>Clip</strong> extracts the portion of one layer that falls within another layer\'s boundary. <strong>Intersect</strong> and <strong>Union</strong> combine two layers based on their spatial overlap, keeping attributes from both. <strong>Dissolve</strong> merges adjacent features sharing a common attribute value into a single feature.</p><h2>Building repeatable workflows</h2><p>Rather than running tools one-off, chain them together using ModelBuilder or Python scripting once a workflow becomes routine, so it can be re-run consistently as new data arrives, without manually repeating each step.</p><h2>Checking your results</h2><p>Always visually and numerically sanity-check geoprocessing output. A quick check, does the output feature count and total area look reasonable, catches a surprising number of input data or parameter errors before they propagate further into an analysis.</p>',
                        ],
                        [
                            'title' => 'Designing and exporting maps',
                            'minutes' => 45,
                            'content' => '<h2>From map view to layout</h2><p>The Map view you have used throughout this course is for working with data. A separate <strong>Layout view</strong> is used to design a finished, presentation-ready map, complete with title, legend, scale bar, north arrow and source notes.</p><h2>Building a layout</h2><p>Insert a map frame into your layout, then add the standard cartographic elements. Keep legend entries limited to layers actually relevant to the map\'s message, and write a legend and title in plain language your intended audience will understand, not internal GIS terminology.</p><h2>Setting an appropriate scale</h2><p>Choose a map scale that matches your area of interest and the level of detail your data actually supports, zooming to unnecessarily fine detail than your source data can accurately support creates a false impression of precision.</p><h2>Exporting your final map</h2><p>Export to PDF for print-quality sharing or PNG/JPEG for digital use, checking the resolution setting matches your intended use (300 DPI minimum for anything that may be printed). This is typically the final deliverable stakeholders actually see, so give it the same care as the analysis behind it.</p>',
                        ],
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
                        [
                            'title' => 'Spatial thinking for programme managers',
                            'minutes' => 40,
                            'content' => '<h2>Why programme managers need spatial thinking, not just GIS software</h2><p>You do not need to become a GIS technician to benefit from spatial thinking. This lesson focuses on the habit of asking location-aware questions, where are our facilities, who can actually reach them, and where is our programme underperforming geographically, that shapes better decisions even before any map is produced.</p><h2>Common programme questions with a spatial answer</h2><p>"Why is uptake low in this district" often has a spatial component: distance, terrain, or seasonal road access. "Where should our next facility go" is fundamentally a coverage question. "Are we reaching the most vulnerable areas" requires overlaying service data with need data. Recognising these as spatial questions is the first step to answering them well.</p><h2>What this course will equip you to do</h2><p>By the end of this course you will be able to read and commission facility and catchment maps, identify coverage gaps, and build recurring spatial reports for programme reviews, working alongside GIS specialists even without becoming one yourself.</p><h2>Working with GIS specialists effectively</h2><p>Programme managers who understand basic spatial concepts ask better questions of their GIS teams and can judge whether a map output actually answers the operational question at hand, rather than simply looking impressive.</p>',
                        ],
                        [
                            'title' => 'Mapping facility locations and catchments',
                            'minutes' => 45,
                            'content' => '<h2>Facility mapping basics</h2><p>A facility map plots every health facility as a point, typically symbolised by type (hospital, clinic, outreach post) and sometimes by operational status. This simple map is the foundation for almost every other health-programme spatial analysis.</p><h2>What a catchment area represents</h2><p>A facility\'s catchment area is the geographic zone from which it draws patients, commonly estimated using a fixed-distance buffer (e.g. 5km) or, more accurately, a travel-time estimate accounting for roads and terrain.</p><h2>Reading a catchment map as a manager</h2><p>Look for three things: overlapping catchments (potential resource duplication), large gaps between catchments (potential coverage gaps), and catchments that do not align with where population actually lives (a mismatch between facility placement and need).</p><h2>Using catchment maps in planning conversations</h2><p>Bring catchment maps into programme planning discussions as a starting point for questions, not a final answer: a gap on the map might be well served by an existing outreach programme not shown, so treat the map as a prompt for local verification, not a substitute for it.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Tracking Coverage and Service Delivery',
                    'lessons' => [
                        [
                            'title' => 'Identifying service delivery gaps',
                            'minutes' => 50,
                            'content' => '<h2>Defining a "gap" precisely</h2><p>A service delivery gap can mean several different things: a geographic area with no facility within reasonable reach, a facility that exists but lacks capacity or specific services, or a population group that is technically covered but not actually utilising services. Be explicit about which type of gap you are analysing.</p><h2>Layering data to find gaps</h2><p>Combine your facility/catchment layer with population distribution data and, where available, service utilisation data. Areas with high population, low facility coverage and low utilisation are the strongest gap candidates for programme attention.</p><h2>Prioritising among multiple gaps</h2><p>Most programmes cannot close every gap at once. Rank identified gaps by population affected, severity of need, and feasibility of intervention, similar to the prioritisation logic used in other spatial planning contexts covered elsewhere in this catalogue.</p><h2>Validating gaps with local knowledge</h2><p>Before committing resources based on a gap analysis, cross-check findings with field or facility staff who may know of informal services, seasonal access issues or population movements the underlying data does not capture.</p>',
                        ],
                        [
                            'title' => 'Combining GIS with programme indicators',
                            'minutes' => 45,
                            'content' => '<h2>Beyond location: linking geography to performance</h2><p>Health programmes already track indicators like immunisation coverage, ANC visit rates, or stockout frequency. This lesson covers joining those indicators to your facility or catchment geography so performance can be viewed and compared spatially, not just in a table.</p><h2>The join process</h2><p>Programme indicator data usually lives in a separate system (a spreadsheet or DHIS2 export) and must be joined to your spatial layer using a shared identifier, typically a facility code or name, which is why consistent facility naming across systems matters so much.</p><h2>Reading spatial indicator patterns</h2><p>Once joined, a choropleth or graduated-symbol map of an indicator (e.g. immunisation coverage by facility) often reveals regional performance patterns invisible in a flat table, such as a whole cluster of underperforming facilities sharing a common supply route or district.</p><h2>Avoiding causal overreach</h2><p>A spatial pattern in an indicator suggests a hypothesis worth investigating, such as a shared supply chain issue, but is not itself proof of a cause. Treat these patterns as a starting point for further programme investigation, not a finished explanation.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Reporting for Programme Management',
                    'lessons' => [
                        [
                            'title' => 'Building recurring spatial reports',
                            'minutes' => 45,
                            'content' => '<h2>Why "recurring" changes the design</h2><p>A one-off spatial analysis can be built any way that gets the answer. A recurring report, produced monthly or quarterly for programme reviews, needs a repeatable, low-effort production process, or it will quietly stop being produced once the person who built it moves on.</p><h2>Standardising the report template</h2><p>Fix the layout, map extent, symbology and included indicators once, so each reporting cycle only requires refreshing the underlying data, not rebuilding the report from scratch. This consistency also helps reviewers compare period to period more easily.</p><h2>Choosing what belongs in a recurring report</h2><p>Include only the indicators and maps that genuinely inform a recurring decision. It is tempting to add more each cycle; resist this, since an overloaded recurring report gets skimmed rather than read.</p><h2>Building institutional resilience</h2><p>Document the production steps (data sources, refresh process, template location) so the report can survive staff turnover, this documentation step is frequently skipped and is usually the reason recurring reports quietly die within a year.</p>',
                        ],
                        [
                            'title' => 'Presenting spatial evidence to donors',
                            'minutes' => 35,
                            'content' => '<h2>What donors are actually looking for</h2><p>Donor audiences typically want evidence that a programme is reaching intended populations, using resources efficiently, and responding to identified gaps. Spatial evidence is powerful here because it makes coverage and targeting claims visually verifiable rather than just asserted in narrative text.</p><h2>Structuring donor-facing spatial evidence</h2><p>Pair a coverage or gap map with a short, specific narrative: what the map shows, how it aligns with the programme\'s targeting strategy, and what action resulted from the finding. Donors respond well to evidence of a decision-making loop, not just a static map.</p><h2>Common donor questions to anticipate</h2><p>Be ready to explain your data sources and their recency, how population estimates were derived, and how any identified gaps are being addressed in the current or next programme phase.</p><h2>Balancing transparency and framing</h2><p>Be honest about remaining gaps rather than only showing favourable coverage; donors generally trust programmes more, not less, when they can see gaps are identified and being actively managed rather than hidden.</p>',
                        ],
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
                        [
                            'title' => 'How satellite imagery works',
                            'minutes' => 45,
                            'content' => '<h2>The physics, briefly</h2><p>Satellite sensors measure electromagnetic radiation, primarily sunlight, reflected off the Earth\'s surface. Different materials (vegetation, soil, water, buildings) reflect and absorb different wavelengths in distinctive patterns, which is what lets a sensor distinguish a forest from a field even though both simply look "green" to a casual glance.</p><h2>Spectral bands</h2><p>Beyond the visible red, green and blue bands the human eye sees, satellites also capture near-infrared and shortwave-infrared bands invisible to us but highly diagnostic for vegetation. Healthy vegetation strongly reflects near-infrared light, which is the basis for vegetation indices like NDVI.</p><h2>Resolution trade-offs</h2><p>Spatial resolution (pixel size) trades off against coverage area and, often, cost and revisit frequency. Sentinel-2\'s 10m resolution and ~5-day revisit is well suited to forest-scale monitoring; very high resolution commercial imagery captures individual trees but is far more costly to acquire at scale and less frequently updated.</p><h2>Why this matters for forestry</h2><p>Understanding these fundamentals lets you choose the right imagery source for a given forestry question, rather than defaulting to whatever imagery happens to be easiest to obtain, which is a common and costly mistake in remote sensing projects.</p>',
                        ],
                        [
                            'title' => 'Sourcing free and commercial imagery',
                            'minutes' => 40,
                            'content' => '<h2>Free imagery sources</h2><p>Sentinel-2 (via Copernicus) and Landsat (via USGS EarthExplorer) are the two primary free, global, regularly updated satellite imagery sources, and they cover the vast majority of sustainable forestry monitoring needs at programme scale.</p><h2>When commercial imagery is worth the cost</h2><p>Commercial very-high-resolution imagery (sub-metre) is justified when you need to detect individual tree-level change, verify small-scale illegal logging, or produce imagery for a specific small, high-value area where free imagery\'s resolution is genuinely insufficient.</p><h2>Cloud cover and revisit considerations</h2><p>Persistent cloud cover in tropical forest regions can make finding a clear image for your exact date of interest difficult. Build flexibility into your monitoring schedule, or consider radar-based imagery (which penetrates cloud) for consistently cloudy regions.</p><h2>Building a sourcing workflow</h2><p>Establish a routine process, define your area of interest once, set up automated alerts or regular checks for new clear-sky imagery, so ongoing monitoring does not require manually re-searching image archives each time.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Forest Monitoring Techniques',
                    'lessons' => [
                        [
                            'title' => 'Classifying forest cover from imagery',
                            'minutes' => 55,
                            'content' => '<h2>The classification task</h2><p>Classification assigns each pixel in an image to a category, typically forest, non-forest, water, and sometimes forest sub-types like dense canopy versus degraded forest. This is the foundation for nearly all subsequent forest monitoring analysis.</p><h2>Supervised vs unsupervised approaches</h2><p><strong>Supervised classification</strong> uses training samples you manually label (this pixel is forest, that one is cropland) to train a classifier that then labels the rest of the image. <strong>Unsupervised classification</strong> automatically groups pixels by spectral similarity, which you then interpret and label afterward. Supervised methods generally produce more reliable, interpretable results when good training data is available.</p><h2>Using vegetation indices to assist classification</h2><p>NDVI thresholds provide a simple, fast first-pass forest/non-forest split before more sophisticated classification, and are often accurate enough for programme-level monitoring on their own.</p><h2>Accuracy assessment</h2><p>Always validate your classification against a set of independent reference points (field visits or very-high-resolution imagery) and report an accuracy percentage. An unvalidated classification is a draft, not a finding, and should not be presented to stakeholders as definitive.</p>',
                        ],
                        [
                            'title' => 'Detecting deforestation and change',
                            'minutes' => 50,
                            'content' => '<h2>Building on classification for change detection</h2><p>Once you can classify forest cover for a single date, detecting deforestation means repeating that classification consistently across multiple dates and comparing the results, exactly the change-detection workflow introduced in earlier natural resource management content, applied specifically to forestry.</p><h2>Choosing a monitoring interval</h2><p>Annual monitoring is common for general forestry programme reporting; more frequent monitoring (monthly or even near-real-time alert systems) is used for active enforcement against illegal logging, where fast detection matters more than long-term trend accuracy.</p><h2>Using existing global forest-change datasets</h2><p>Global datasets like Global Forest Watch already provide pre-computed annual forest-loss layers for much of the world, which can supplement or cross-validate your own classification-based analysis rather than requiring you to build everything from scratch.</p><h2>Distinguishing loss types</h2><p>Where possible, distinguish permanent deforestation (conversion to another land use) from temporary disturbance (selective logging that will regrow, or natural disturbance like fire), since these carry very different programme and policy implications.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Planning and Reporting',
                    'lessons' => [
                        [
                            'title' => 'Integrating GIS and remote sensing outputs',
                            'minutes' => 50,
                            'content' => '<h2>Combining imagery-derived layers with other GIS data</h2><p>Remote sensing gives you forest cover and change layers; combining these with other GIS data, protected area boundaries, concession maps, road networks, community land claims, turns raw change detection into planning-relevant intelligence.</p><h2>Overlay questions worth asking</h2><p>Does detected forest loss fall inside a protected area (a compliance concern)? Is loss concentrated near new road access (a common deforestation driver)? Does loss overlap community-claimed land (a social and legal concern)? Each of these overlay questions turns a change map into an actionable finding.</p><h2>Building an integrated monitoring layer</h2><p>Rather than keeping remote sensing outputs and other GIS layers as separate maps, build a single integrated dataset combining them, so any user pulling up the forest monitoring map automatically sees the relevant planning context alongside it.</p><h2>Keeping context layers current</h2><p>Boundary and concession data changes too, not just forest cover. Establish a process for periodically refreshing these context layers alongside your imagery updates, or your integrated analysis will gradually become misleading even if the forest-cover data itself stays current.</p>',
                        ],
                        [
                            'title' => 'Building forestry monitoring reports',
                            'minutes' => 40,
                            'content' => '<h2>What a forestry monitoring report needs to answer</h2><p>A well-designed periodic report answers three core questions: how much forest was lost or gained this period, where specifically, and how does this compare to the previous period or a longer-term baseline.</p><h2>Structuring the report</h2><p>Lead with a headline summary statistic (total hectares changed), follow with a map showing the spatial distribution of change, and close with a comparison to prior periods or targets, so readers immediately understand both the current picture and the trajectory.</p><h2>Balancing detail and accessibility</h2><p>Technical staff may want classification accuracy figures and methodology notes; programme and donor audiences generally want the headline numbers and a clear map. Consider a short main report with technical methodology as an appendix, rather than mixing both audiences into one dense document.</p><h2>Closing the reporting loop</h2><p>Wherever possible, note what action the previous report\'s findings led to. This turns your monitoring report from a static record into visible evidence that spatial monitoring is actually informing forestry programme decisions.</p>',
                        ],
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
                        [
                            'title' => 'GIS concepts for disaster risk',
                            'minutes' => 40,
                            'content' => '<h2>Risk as the intersection of three layers</h2><p>Disaster risk is commonly framed as the intersection of <strong>hazard</strong> (the physical event, flood, earthquake, drought), <strong>exposure</strong> (people and assets located where the hazard could occur) and <strong>vulnerability</strong> (how susceptible those people and assets are to harm). GIS is the natural tool for this framework because all three components are fundamentally spatial.</p><h2>Why mapping alone is not enough</h2><p>A hazard map on its own does not tell you risk, a flood zone with no population is a very different risk than one with a dense settlement. This course builds toward combining hazard, exposure and vulnerability layers into a genuine composite risk picture, not just a hazard extent map.</p><h2>Types of hazards you will encounter</h2><p>Hazards vary in how predictable and how suddenly they onset: floods and cyclones often have some lead time and established modelling approaches; earthquakes are unpredictable in timing but their potential impact zones can still be mapped; drought develops slowly and is tracked through indicators over time rather than a single event map.</p><h2>Setting expectations for this course</h2><p>You will build hazard zone maps, vulnerability assessments, and combine them into composite risk maps that support real disaster response planning, the same workflow used by professional disaster risk management teams.</p>',
                        ],
                        [
                            'title' => 'Mapping hazard zones',
                            'minutes' => 45,
                            'content' => '<h2>Sources of hazard data</h2><p>Depending on hazard type, data sources include historical flood extent records, modelled flood or storm-surge zones, fault-line and seismic hazard data for earthquakes, and drought indices derived from rainfall and vegetation data over time.</p><h2>Historical vs modelled hazard zones</h2><p>Historical hazard maps (based on past events) are useful but can understate risk if the worst historical event was smaller than a plausible future one. Modelled hazard zones (e.g. 1-in-100-year flood extent) attempt to capture a fuller range of possible severity, but depend heavily on the quality of the underlying model and data.</p><h2>Combining hazard types</h2><p>Many areas face more than one hazard type. Where relevant, map each hazard separately first, since combining them prematurely can obscure which specific hazard is driving risk in a given area, before considering any combined multi-hazard view.</p><h2>Communicating hazard uncertainty</h2><p>Hazard zones, especially modelled ones, carry uncertainty. Clearly label whether a zone represents a historical record, a probabilistic model, or expert judgement, so users do not treat a modelled boundary as a precise, guaranteed line.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Vulnerability and Exposure Analysis',
                    'lessons' => [
                        [
                            'title' => 'Assessing population vulnerability',
                            'minutes' => 50,
                            'content' => '<h2>Vulnerability is not just location</h2><p>Two households in the same flood zone can face very different actual risk depending on factors like housing construction quality, access to early warning information, mobility (including age, disability), and economic capacity to recover afterward. Vulnerability assessment tries to capture these differences, not just physical exposure.</p><h2>Common vulnerability indicators</h2><p>Typical indicators include poverty rates, presence of vulnerable groups (elderly, young children, people with disabilities), housing quality, and access to services. These are usually sourced from census data, household surveys, or existing vulnerability indices where available.</p><h2>Building a vulnerability layer</h2><p>Combine selected indicators into a composite vulnerability score per administrative unit, using a transparent, documented weighting approach, following the same normalise-and-weight logic used in food-security vulnerability mapping elsewhere in this catalogue.</p><h2>Ground-truthing vulnerability assessments</h2><p>Wherever feasible, validate indicator-based vulnerability assessments against local knowledge or community consultation; indicators can miss locally significant factors, such as recent displacement, that only local informants would know to flag.</p>',
                        ],
                        [
                            'title' => 'Mapping critical infrastructure exposure',
                            'minutes' => 45,
                            'content' => '<h2>What counts as critical infrastructure</h2><p>Critical infrastructure for disaster planning typically includes health facilities, schools (often used as shelters), water and power infrastructure, and key transport routes needed for evacuation or relief access.</p><h2>Overlaying infrastructure with hazard zones</h2><p>Simple overlay analysis, does a given hazard zone intersect a hospital, a school, a key bridge, immediately surfaces which specific critical assets are at risk, information that shapes both preparedness investment and response prioritisation.</p><h2>Beyond simple intersection: functional impact</h2><p>Consider not just whether an asset sits inside a hazard zone, but whether damage to it would cut off access to other areas, a single bridge on an otherwise isolated road can represent risk far beyond its own physical footprint.</p><h2>Keeping infrastructure data current</h2><p>Infrastructure data goes stale: new health facilities open, roads are built or washed out. Establish a periodic review process for critical infrastructure layers, since an exposure analysis built on outdated infrastructure data can miss genuinely critical assets.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Risk Mapping and Response Planning',
                    'lessons' => [
                        [
                            'title' => 'Building composite risk maps',
                            'minutes' => 50,
                            'content' => '<h2>Combining hazard, exposure and vulnerability</h2><p>A composite risk map combines the hazard zones, exposure layers and vulnerability assessment built in earlier lessons into a single risk classification, typically using a weighted overlay approach similar to other multi-criteria mapping covered in this catalogue.</p><h2>Choosing a risk classification scheme</h2><p>Use a small number of clear risk categories (e.g. low, moderate, high, very high) rather than a continuous, hard-to-interpret score, since disaster response audiences generally need to make quick categorical decisions, not fine statistical distinctions.</p><h2>Validating the composite map</h2><p>Sanity-check your composite risk map against known past disaster impacts, areas that suffered significant harm in previous events should generally show as higher risk in your composite map; if they do not, revisit your weighting or input layers.</p><h2>Keeping the model transparent</h2><p>Document exactly which layers and weights produced your composite risk classification. Disaster risk maps often inform high-stakes resource allocation decisions, and stakeholders are right to ask how a given area was classified as it was.</p>',
                        ],
                        [
                            'title' => 'Supporting response planning with GIS',
                            'minutes' => 35,
                            'content' => '<h2>From risk map to response plan</h2><p>A composite risk map identifies where risk is concentrated; response planning uses that information alongside operational data, available response resources, evacuation routes, shelter locations, to build an actionable plan for when a hazard event occurs.</p><h2>Pre-positioning resources</h2><p>Use your risk and infrastructure exposure layers to inform where relief supplies, personnel or equipment should be pre-positioned ahead of a likely event, rather than only mobilising after impact is already underway.</p><h2>Evacuation and access planning</h2><p>Combine hazard zones with the transport network to identify viable evacuation routes that remain usable during the hazard event itself, a route that floods before the population needing to evacuate can use it provides false security.</p><h2>Keeping response plans usable under pressure</h2><p>Response plans built from GIS analysis need to be usable by people in the field during an actual event, often without reliable connectivity. Produce simple, printable map products as a companion to any interactive GIS tool, so the plan remains usable when it matters most.</p>',
                        ],
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
                        [
                            'title' => 'How web mapping applications work',
                            'minutes' => 45,
                            'content' => '<h2>The client-server model for maps</h2><p>A web map application typically has two parts: a server that stores and serves spatial data (as tiles, vector data, or through an API), and a client (the browser, running JavaScript) that requests that data and renders it interactively for the user. Understanding this split is essential before writing any code.</p><h2>Tiles vs vector data on the web</h2><p><strong>Raster tiles</strong> are pre-rendered image squares (like a traditional web map background) that load fast but cannot be styled dynamically. <strong>Vector tiles</strong> send the actual geometry and let the browser style and interact with it directly, enabling things like dynamic styling, click-to-query, and smooth zoom, at the cost of more client-side processing.</p><h2>Why open-source tools</h2><p>Open-source web mapping libraries (this course focuses on Leaflet and, where relevant, OpenLayers) let you build fully custom interactive maps without licensing costs, which is a major reason they are widely used across development, government and civic-tech projects with limited budgets.</p><h2>What you will build</h2><p>Across this course you will prepare spatial data for the web, build an interactive map with real layers and interactivity, and deploy it as a working, publicly accessible application.</p>',
                        ],
                        [
                            'title' => 'Preparing and serving spatial data',
                            'minutes' => 45,
                            'content' => '<h2>Web-friendly data formats</h2><p>Desktop GIS formats like shapefiles are not directly usable on the web. GeoJSON, a text-based, JavaScript-native format, is the standard for vector data in web mapping, since browsers can parse and render it directly.</p><h2>Converting existing GIS data</h2><p>Tools like QGIS or command-line utilities (such as ogr2ogr) can export your existing shapefiles or geodatabase layers to GeoJSON, a necessary conversion step before any data can be used in a web map.</p><h2>Simplifying data for web performance</h2><p>Detailed geometries that look fine in desktop GIS can be far too heavy to load quickly in a browser, especially on mobile connections. Simplify complex polygon boundaries and consider limiting attribute data sent to only what the map interface actually needs to display.</p><h2>Serving data: static files vs an API</h2><p>For smaller, infrequently updated datasets, a static GeoJSON file is often the simplest approach. For larger or frequently updated datasets, serving data through a dedicated spatial API or database (such as PostGIS) becomes worthwhile, a decision this course will help you make based on your specific data\'s size and update frequency.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Building Interactive Maps',
                    'lessons' => [
                        [
                            'title' => 'Working with open-source mapping libraries',
                            'minutes' => 55,
                            'content' => '<h2>Introducing Leaflet</h2><p>Leaflet is a lightweight, widely used open-source JavaScript library for building interactive maps. It handles the core mechanics, map panning, zooming, tile loading, so you can focus on adding your own data and interactivity rather than reimplementing basic map behaviour.</p><h2>Setting up your first map</h2><p>A minimal Leaflet map requires only a container element, a base tile layer (commonly OpenStreetMap tiles, itself a free, open dataset), and an initial view (centre point and zoom level). This handful of lines is the starting point for every project built in this course.</p><h2>Adding your GeoJSON data</h2><p>Once your base map is working, adding your prepared GeoJSON data as a layer is straightforward, Leaflet includes built-in support for rendering GeoJSON directly, with styling options for how points, lines and polygons should appear.</p><h2>Choosing between libraries</h2><p>Leaflet favours simplicity and a gentle learning curve; OpenLayers offers more advanced capabilities for complex projection or large-dataset needs at the cost of a steeper learning curve. This course uses Leaflet as the primary teaching tool since it covers the large majority of real-world project needs.</p>',
                        ],
                        [
                            'title' => 'Adding layers, popups and interactivity',
                            'minutes' => 50,
                            'content' => '<h2>Making a map genuinely interactive</h2><p>A static-looking map with pan and zoom is only the starting point. Interactivity, clicking a feature to see details, toggling layers on and off, filtering by category, is what turns a web map into a genuinely useful tool rather than just an image with zoom.</p><h2>Popups and tooltips</h2><p>Attach popups to features so clicking (or hovering, for tooltips) reveals relevant attribute information. Keep popup content concise and well formatted, a popup crammed with every raw attribute field is far less useful than one showing the three or four details a user actually needs.</p><h2>Layer controls</h2><p>Add a layer control so users can toggle different data layers (e.g. facilities, boundaries, hazard zones) independently, letting a single map serve multiple audiences and use cases without needing separate map products for each.</p><h2>Custom markers and styling</h2><p>Replace default markers with custom icons or colour-coded symbols that match your data\'s categories (matching the cartographic design principles from earlier in this catalogue), since default markers rarely communicate the right information at a glance.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Deployment and Publishing',
                    'lessons' => [
                        [
                            'title' => 'Hosting and deploying a web map',
                            'minutes' => 50,
                            'content' => '<h2>From local development to a live URL</h2><p>A web map that only runs on your own computer has no real-world use. This lesson covers the final step: deploying your application so it is accessible via a public URL that stakeholders can actually open in their own browser.</p><h2>Simple static hosting options</h2><p>For a map built with static HTML, JavaScript and GeoJSON files, low-cost or free static hosting platforms are usually sufficient, no server-side application logic is required for a purely client-side interactive map.</p><h2>Custom domains and access control</h2><p>Depending on your audience, decide whether the map should be fully public or restricted (password-protected or shared only via an unlisted link), a consideration that should be settled before deployment, not retrofitted afterward.</p><h2>Testing before sharing widely</h2><p>Before sharing your deployed map broadly, test it on a mobile device and a slower connection, not just your development machine, since real-world users often have very different device and connectivity conditions than a developer\'s workstation.</p>',
                        ],
                        [
                            'title' => 'Performance and maintenance considerations',
                            'minutes' => 35,
                            'content' => '<h2>Why performance matters for adoption</h2><p>A web map that loads slowly or freezes when panning will simply not get used, regardless of how good the underlying analysis is. Performance is a design constraint, not an afterthought to fix once complaints arrive.</p><h2>Common performance bottlenecks</h2><p>Oversized GeoJSON files (too many features or too much geometric detail), too many simultaneous layers rendered at once, and unoptimised marker icons are the most common causes of a sluggish web map. Address these before adding more features, not after.</p><h2>Practical optimisation techniques</h2><p>Simplify geometry where full precision is not needed, cluster dense point layers at low zoom levels rather than rendering every marker at once, and lazy-load layers so the initial map view stays fast even if the full dataset is large.</p><h2>Planning for ongoing maintenance</h2><p>Decide early who is responsible for updating the underlying data and redeploying the map as it changes. A web map is a living product, not a one-time deliverable, and without a clear maintenance owner it will quietly become outdated.</p>',
                        ],
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
                        [
                            'title' => 'Form design principles',
                            'minutes' => 40,
                            'content' => '<h2>A form is a conversation, designed in advance</h2><p>A digital survey form is really a scripted conversation between an enumerator (or respondent) and the questions being asked. Good form design anticipates confusion before it happens in the field, where fixing a bad question mid-collection is far costlier than fixing it on paper beforehand.</p><h2>Question wording and order</h2><p>Write questions in clear, unambiguous language matched to your respondents\' context, avoid double-barrelled questions (asking two things at once), and order questions logically, general context first, sensitive topics later, once some rapport is established.</p><h2>Choosing the right question type</h2><p>Match question type to the data you need: select-one for mutually exclusive categories, select-multiple for "choose all that apply", numeric for quantities, and free text sparingly, since open text is far harder to analyse at scale than structured responses.</p><h2>Designing for the field, not the desk</h2><p>Forms will be used by enumerators standing in a field or doorway, often under time pressure. Favour shorter forms with clear skip logic over long forms that ask every respondent every question regardless of relevance.</p>',
                        ],
                        [
                            'title' => 'Building forms in XLSForm',
                            'minutes' => 50,
                            'content' => '<h2>What XLSForm is</h2><p>XLSForm is a standard way to author survey forms using a familiar spreadsheet, one row per question, with columns defining type, label, and logic, which is then converted into a deployable digital form. It is the standard authoring format behind both KoboToolbox and ODK, so mastering it here transfers directly.</p><h2>The core XLSForm structure</h2><p>The "survey" sheet holds your questions in order, with columns for question type, name (the variable name used in your dataset), and label (the text shown to the enumerator/respondent). A separate "choices" sheet defines the answer options for select-type questions.</p><h2>Adding skip logic</h2><p>Use the "relevant" column to show or hide a question based on a previous answer, for example, only asking follow-up questions about a health facility visit if the respondent indicated they had visited one. This keeps forms shorter and more relevant for each respondent.</p><h2>Adding validation constraints</h2><p>Use the "constraint" column to catch impossible or unlikely answers at the point of entry, such as an age over 120 or a date in the future, catching an error while the enumerator is still with the respondent is far more valuable than catching it during later data cleaning.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Deploying Surveys with Ona and Kobo',
                    'lessons' => [
                        [
                            'title' => 'Setting up a project in KoboToolbox',
                            'minutes' => 45,
                            'content' => '<h2>From form design to a live project</h2><p>Once your XLSForm is built, KoboToolbox lets you upload it directly to create a live, deployable project. This lesson walks through account setup, uploading your form, and configuring basic project settings before deployment.</p><h2>Reviewing your form before deployment</h2><p>KoboToolbox provides a preview mode that simulates exactly what an enumerator will see. Always walk through this preview end-to-end, including every skip-logic branch, before deploying to the field, since form errors are far cheaper to fix here than after data collection begins.</p><h2>Setting permissions and data sharing</h2><p>Configure who can view, edit or submit to your project, particularly important when multiple enumerators or partner organisations are involved. Getting permissions right from the start avoids both data security issues and workflow bottlenecks later.</p><h2>Project settings that matter</h2><p>Review settings for data encryption (relevant for sensitive data), submission notifications, and whether the form should require an internet connection or work fully offline, a decision that should be based on your field team\'s actual connectivity conditions.</p>',
                        ],
                        [
                            'title' => 'Deploying and testing on mobile devices',
                            'minutes' => 40,
                            'content' => '<h2>Getting the form onto a device</h2><p>Once deployed, enumerators access the form either through a mobile browser or a dedicated app (such as KoboCollect), which downloads the form definition for offline use, submitting collected data whenever a connection becomes available.</p><h2>Offline-first data collection</h2><p>A major advantage of tools like Kobo and Ona is offline capability: enumerators can collect complete surveys without connectivity and sync later. Always test this offline flow specifically, since a form that works fine on office wifi can behave unexpectedly with no connection at all.</p><h2>Field-testing before full rollout</h2><p>Before sending a full team to the field, have two or three enumerators pilot the deployed form under realistic conditions, this catches device-specific issues, confusing skip logic, and unrealistic completion-time estimates that a desk review alone would miss.</p><h2>Troubleshooting common device issues</h2><p>Common early problems include forms not syncing due to storage or permission issues, and inconsistent GPS accuracy indoors. Build a short troubleshooting checklist for field teams so common issues do not require escalating back to the office every time.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Managing and Exporting Field Data',
                    'lessons' => [
                        [
                            'title' => 'Monitoring incoming submissions',
                            'minutes' => 35,
                            'content' => '<h2>Why active monitoring matters during collection</h2><p>Data collection is not "set and forget" once forms are deployed. Actively monitoring incoming submissions during the collection period lets you catch problems, low submission rates from a specific enumerator, unusual patterns suggesting fabricated data, while there is still time to intervene.</p><h2>Using the submission dashboard</h2><p>KoboToolbox and Ona both provide a live submission view showing counts, timing, and basic summary statistics as data comes in. Check this regularly rather than waiting until the end of collection to look at your data for the first time.</p><h2>Spotting data quality red flags early</h2><p>Watch for enumerators with unusually fast completion times (possibly skipping or fabricating responses), high rates of missing or "don\'t know" answers on key questions, or submissions clustering around a time and location that does not make sense for the assigned sample.</p><h2>Following up in the field</h2><p>When monitoring surfaces a concern, follow up directly with the enumerator or supervisor while the team is still in the field, retraining or correcting an issue mid-collection is far more effective than discovering it only after the team has demobilised.</p>',
                        ],
                        [
                            'title' => 'Exporting and cleaning collected data',
                            'minutes' => 40,
                            'content' => '<h2>Exporting from Kobo or Ona</h2><p>Both platforms allow exporting collected data in standard formats (CSV, Excel, or direct integration with analysis tools), with options to export raw submissions or, for select-multiple and repeat-group questions, a flattened structure easier to work with in standard spreadsheet or statistical software.</p><h2>First-pass cleaning steps</h2><p>Check for duplicate submissions (which can occur from connectivity issues causing double-syncs), verify date ranges fall within your actual collection period, and confirm categorical responses match your expected choice list exactly.</p><h2>Handling "other, please specify" responses</h2><p>Open-text "other" responses accumulate quickly and need review, some may actually belong to an existing category and should be recoded, while genuinely new categories may need to be added to your coding scheme.</p><h2>Documenting your cleaning process</h2><p>Keep a clear record of every cleaning decision, records removed as duplicates, values recoded, so the cleaning process is transparent and reproducible rather than a black box between the raw export and your final analysis dataset.</p>',
                        ],
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
                        [
                            'title' => 'The ODK ecosystem explained',
                            'minutes' => 35,
                            'content' => '<h2>What ODK is</h2><p>Open Data Kit (ODK) is a free, open-source suite of tools for mobile data collection, one of the foundational platforms the wider digital data collection ecosystem (including KoboToolbox) is built on top of. Understanding ODK\'s core components gives you a transferable foundation across many similar tools.</p><h2>The core ODK components</h2><p><strong>ODK Build/XLSForm</strong> is used to design forms. <strong>ODK Collect</strong> is the Android app enumerators use to fill out forms in the field, including fully offline. <strong>ODK Central</strong> (or a compatible server) manages form deployment, collects submissions, and provides basic monitoring and export capabilities.</p><h2>Why organisations choose ODK specifically</h2><p>ODK is fully open-source and self-hostable, which matters for organisations with strict data sovereignty requirements or limited budget for hosted platform fees, an important distinction from hosted platforms like Kobo or Ona covered elsewhere in this catalogue.</p><h2>What this course covers</h2><p>You will design ODK forms with skip logic and validation, deploy them to ODK Collect, and manage the resulting field data through to a clean, export-ready dataset.</p>',
                        ],
                        [
                            'title' => 'Setting up your first ODK project',
                            'minutes' => 40,
                            'content' => '<h2>Choosing your server setup</h2><p>ODK Central can be self-hosted on your own server infrastructure, or accessed via a managed hosting option, a decision that depends on your organisation\'s technical capacity and data governance requirements.</p><h2>Creating your first project</h2><p>Within ODK Central, a project is a container for one or more related forms, useful for organising work by programme, survey round, or team. Set up a dedicated project before uploading any forms, rather than mixing unrelated forms together.</p><h2>Uploading and publishing a form</h2><p>Forms authored in XLSForm are uploaded to your project and then published, at which point they become available for enumerators to download via ODK Collect. Always preview a newly uploaded form before publishing it live to your field team.</p><h2>Managing user accounts and roles</h2><p>ODK Central supports role-based access, giving enumerators submission-only access while giving supervisors or analysts broader viewing and management rights, set these up deliberately rather than defaulting everyone to full access.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Form Design in ODK',
                    'lessons' => [
                        [
                            'title' => 'Building forms with skip logic',
                            'minutes' => 50,
                            'content' => '<h2>Skip logic in the ODK/XLSForm context</h2><p>As in the broader XLSForm standard, skip logic in ODK is implemented through the "relevant" column, controlling whether a question is shown based on previous answers, keeping each respondent\'s experience focused only on questions that actually apply to them.</p><h2>Grouping related questions</h2><p>Use "begin group"/"end group" structures to organise related questions, useful both for applying shared skip logic to a whole section at once and for keeping your form structure readable as it grows in complexity.</p><h2>Repeat groups for variable-count data</h2><p>When a respondent needs to report on a variable number of items, such as each household member or each crop grown, ODK\'s repeat group feature lets the enumerator add as many repetitions as needed, rather than pre-allocating a fixed number of slots that may not match reality.</p><h2>Testing skip logic thoroughly</h2><p>Before deployment, test every branch of your skip logic, not just the "typical" path through the form, since an untested rare branch is a common source of field data collection failures discovered only after data is already coming in.</p>',
                        ],
                        [
                            'title' => 'Adding validation and constraints',
                            'minutes' => 40,
                            'content' => '<h2>Why validation belongs in the form, not just post-collection</h2><p>Every constraint you build into the form itself is an error that never needs to be caught, cleaned or queried later. This is significantly more efficient than relying entirely on post-collection data cleaning.</p><h2>Constraint expressions</h2><p>ODK\'s "constraint" column accepts logical expressions to restrict acceptable answers, such as requiring a numeric answer to fall within a plausible range, or requiring an end date to be after a start date already entered earlier in the form.</p><h2>Writing helpful constraint messages</h2><p>Pair every constraint with a clear "constraint_message" explaining what went wrong and what is expected, a generic "invalid entry" message leaves the enumerator guessing, while a specific message speeds up correct data entry.</p><h2>Required vs optional fields</h2><p>Mark genuinely essential questions as required, but use this sparingly, over-using required fields can force enumerators to enter placeholder or fabricated answers to a question that legitimately does not apply, which is worse for data quality than leaving it appropriately blank.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Field Deployment and Data Management',
                    'lessons' => [
                        [
                            'title' => 'Deploying to ODK Collect',
                            'minutes' => 40,
                            'content' => '<h2>Getting the app set up</h2><p>ODK Collect is installed on enumerators\' Android devices and configured to connect to your ODK Central project, after which published forms become available to download for offline use in the field.</p><h2>Managing multiple form versions</h2><p>When you update a form after initial deployment, ODK versions it, ensuring enumerators are using the current version and that submissions are correctly matched to the form version they were collected against, an important detail for keeping your dataset internally consistent.</p><h2>Working fully offline</h2><p>A core ODK strength is complete offline functionality: forms download once with connectivity, and completed submissions queue locally until the device regains a connection to sync. Confirm this works as expected for your specific device models before full field rollout.</p><h2>Supervising a distributed field team</h2><p>For larger teams, establish a simple daily check-in routine (sync status, submission counts, any device issues) so problems are caught within a day rather than only discovered once the full team returns from the field.</p>',
                        ],
                        [
                            'title' => 'Quality-checking and exporting data',
                            'minutes' => 40,
                            'content' => '<h2>Reviewing submissions in ODK Central</h2><p>ODK Central provides a submission review interface where you can view, and where needed flag or reject, individual submissions before they are treated as final, useful for catching enumerator errors identified after the fact.</p><h2>Cross-checking against expected sample</h2><p>Compare actual submissions against your planned sample (by location, enumerator, or target group) to identify under- or over-representation early enough to still send a team back to fill a gap.</p><h2>Exporting for analysis</h2><p>ODK Central exports data as CSV, with repeat groups exported as linked tables you will need to join back to the main dataset using a shared submission identifier, a structural detail worth planning for before you reach the analysis stage.</p><h2>Archiving and data retention</h2><p>Establish a clear policy for how long raw submission data (including any collected media or GPS traces) is retained and how it is securely archived once a project concludes, particularly important when the data includes personally identifiable information.</p>',
                        ],
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
                        [
                            'title' => 'Choosing a research methodology',
                            'minutes' => 45,
                            'content' => '<h2>Starting with the research question, not the method</h2><p>A common mistake is choosing a methodology (a survey, a set of interviews) before the research question is fully clarified. The right method follows from what you are actually trying to learn, quantitative methods for "how much/how many" questions, qualitative methods for "why/how" questions, and mixed methods when you genuinely need both.</p><h2>Quantitative approaches</h2><p>Quantitative research (surveys, structured data collection) produces numeric data suited to statistical analysis and generalisation to a wider population, provided the sample is appropriately designed, which is the focus of the next lesson.</p><h2>Qualitative approaches</h2><p>Qualitative research (interviews, focus groups, case studies) produces rich, contextual understanding of experiences and reasons, but is not designed to produce statistically generalisable numeric findings, a distinction worth being explicit about when planning and later reporting.</p><h2>Setting up for this course</h2><p>This course focuses primarily on the quantitative side, from design through SPSS-based analysis, though the design principles in this module apply regardless of which method you ultimately choose.</p>',
                        ],
                        [
                            'title' => 'Sampling design and questionnaire planning',
                            'minutes' => 45,
                            'content' => '<h2>Why sampling design determines what you can claim</h2><p>How you select your sample determines whether your findings can be generalised to a wider population, and with what confidence. A poorly designed sample undermines even the most sophisticated statistical analysis performed on it later.</p><h2>Probability vs non-probability sampling</h2><p><strong>Probability sampling</strong> (random, stratified, cluster) gives every unit in the population a known chance of selection, enabling statistically valid generalisation. <strong>Non-probability sampling</strong> (convenience, purposive) is often faster and cheaper but limits how confidently findings can be generalised beyond the sample itself.</p><h2>Determining sample size</h2><p>Sample size should be calculated based on your desired confidence level, margin of error, and expected variability in the population, not chosen arbitrarily or purely based on budget. Standard sample size formulas or calculators can guide this decision once these parameters are set.</p><h2>Questionnaire planning</h2><p>Draft your questionnaire alongside your analysis plan, for each planned analysis, confirm you are actually asking the question that will produce the needed data, a check that catches gaps far more cheaply before data collection than after.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Data Management in SPSS',
                    'lessons' => [
                        [
                            'title' => 'Importing and structuring data in SPSS',
                            'minutes' => 40,
                            'content' => '<h2>SPSS\'s two core views</h2><p>SPSS organises work around two linked views: <strong>Data View</strong>, where each row is a case (respondent) and each column a variable, and <strong>Variable View</strong>, where you define each variable\'s properties, name, type, labels, and measurement level.</p><h2>Importing data</h2><p>SPSS can import data directly from Excel, CSV, or other statistical software formats. After import, always verify variable types were interpreted correctly, a numeric code variable imported as text, for instance, will silently break later statistical procedures.</p><h2>Defining variable and value labels</h2><p>Assign clear variable labels (full question text) and value labels (e.g. 1 = "Male", 2 = "Female") so your output tables are self-explanatory, rather than showing only raw numeric codes that require constantly cross-checking a separate codebook.</p><h2>Setting the correct measurement level</h2><p>SPSS distinguishes nominal, ordinal and scale (interval/ratio) measurement levels, which determines which statistical procedures and charts are appropriate and available for a given variable, get this right early, since many later menu options depend on it.</p>',
                        ],
                        [
                            'title' => 'Data cleaning and recoding',
                            'minutes' => 45,
                            'content' => '<h2>Systematic data cleaning in SPSS</h2><p>Before any analysis, run frequency tables on every variable to spot out-of-range values, unexpected codes, or suspicious patterns, this simple first step catches a large share of data entry and import errors before they contaminate your analysis.</p><h2>Handling missing data</h2><p>Distinguish between different types of missing data (skipped due to skip logic, refused to answer, genuinely not collected) and code them with distinct missing-value codes in SPSS, rather than leaving all gaps as a single undifferentiated blank.</p><h2>Recoding variables</h2><p>Use SPSS\'s "Recode into Different Variables" function (preserving your original data) to create new variables, such as collapsing a detailed age variable into broader age-group categories for cross-tabulation, always recoding into a new variable rather than overwriting the original.</p><h2>Computing new variables</h2><p>Use the Compute function to derive new variables from existing ones, such as a composite score from several related survey items, documenting the computation logic clearly so it can be checked or replicated later.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Statistical Analysis and Reporting',
                    'lessons' => [
                        [
                            'title' => 'Descriptive statistics and cross-tabulation',
                            'minutes' => 50,
                            'content' => '<h2>Starting with descriptives, always</h2><p>Before any inferential test, run basic descriptive statistics, means, frequencies, standard deviations, on every key variable. This both checks data quality and builds the foundational understanding needed to interpret more advanced analysis correctly later.</p><h2>Choosing the right descriptive statistic</h2><p>Use frequencies and percentages for categorical variables, and mean/median with standard deviation for numeric variables, checking whether the mean or median is more appropriate based on whether the distribution is skewed.</p><h2>Cross-tabulation for relationships</h2><p>Cross-tabulation (Analyze > Descriptive Statistics > Crosstabs in SPSS) shows how two categorical variables relate, such as service satisfaction by region, a common and highly interpretable first step in exploring relationships in your data before formal hypothesis testing.</p><h2>Presenting descriptive output clearly</h2><p>SPSS output tables are dense by default; when preparing results for a report, simplify tables to show only the figures relevant to your specific finding, rather than pasting an entire raw SPSS output table into a stakeholder-facing document.</p>',
                        ],
                        [
                            'title' => 'Inferential tests and interpretation',
                            'minutes' => 55,
                            'content' => '<h2>What inferential statistics add</h2><p>Descriptive statistics summarise your sample; inferential statistics let you draw conclusions about the wider population and test whether an observed difference or relationship is likely real or could plausibly have occurred by chance alone.</p><h2>Choosing the right test</h2><p>Match your test to your data: a chi-square test for relationships between two categorical variables, an independent-samples t-test for comparing means between two groups, and correlation or regression for relationships between numeric variables, choosing incorrectly is one of the most common statistical errors in applied research.</p><h2>Interpreting p-values correctly</h2><p>A p-value below your chosen threshold (commonly 0.05) suggests the observed result is unlikely to have occurred by chance alone, it does not, on its own, indicate a large or practically important effect, always consider effect size alongside statistical significance.</p><h2>Checking test assumptions</h2><p>Most inferential tests carry assumptions (such as approximately normal distribution for a t-test). SPSS provides diagnostic tools to check these; running a test whose assumptions are clearly violated can produce misleading results even when the mechanical output looks fine.</p>',
                        ],
                        [
                            'title' => 'Reporting statistical findings',
                            'minutes' => 35,
                            'content' => '<h2>From SPSS output to a readable report</h2><p>Raw SPSS output tables are built for analysis, not for a stakeholder report. This lesson covers translating statistical results into clear written findings that a non-technical reader can follow and trust.</p><h2>Structuring a findings section</h2><p>State the finding first in plain language ("Respondents in urban areas reported significantly higher satisfaction than rural respondents"), then support it with the specific statistic (test used, value, significance level) for readers who want the technical detail.</p><h2>Using tables and charts effectively</h2><p>Select only the tables and charts that support your specific narrative, rather than including every output SPSS produced. A focused, well-labelled chart communicates more than a dense table of numbers few readers will actually parse.</p><h2>Being honest about limitations</h2><p>Every study has limitations, sample size constraints, potential response bias, generalisability limits. Stating these clearly strengthens rather than weakens a report\'s credibility, since it shows the analysis was conducted with appropriate rigour and self-awareness.</p>',
                        ],
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
                        [
                            'title' => 'Donor financial reporting standards',
                            'minutes' => 45,
                            'content' => '<h2>Why donor reporting differs from standard accounting</h2><p>Donor-funded projects must satisfy both standard financial accounting practice and the specific reporting formats, timelines and cost categorisations each individual donor requires, which often differ significantly between funders even for the same underlying project activity.</p><h2>Common donor reporting requirements</h2><p>Most donors require periodic financial reports showing expenditure against approved budget lines, often accompanied by supporting documentation, and many require reporting in the donor\'s own currency and format templates rather than your organisation\'s standard chart of accounts.</p><h2>Building a donor-ready chart of accounts</h2><p>Structure your project accounting so expenditure can be mapped cleanly to donor budget categories from the start, retrofitting a chart of accounts to match donor categories after the fact is a common and largely avoidable source of reporting delays and errors.</p><h2>Meeting reporting deadlines reliably</h2><p>Missed or late donor financial reports can jeopardise future funding and organisational reputation. Build reporting deadlines into your project calendar with sufficient lead time for internal review before submission, not just the donor\'s stated due date.</p>',
                        ],
                        [
                            'title' => 'Budgeting for donor-funded projects',
                            'minutes' => 45,
                            'content' => '<h2>Building a realistic donor budget</h2><p>A donor budget must be detailed enough to satisfy donor scrutiny while remaining realistic and achievable, an overly optimistic budget creates compliance problems later when actual costs diverge, while an overly padded budget risks rejection during proposal review.</p><h2>Direct vs indirect costs</h2><p>Distinguish clearly between direct costs (attributable specifically to the project) and indirect/overhead costs (shared organisational costs allocated proportionally), since donors typically cap indirect cost rates and require a defensible allocation methodology for them.</p><h2>Budget line flexibility and variance</h2><p>Understand your specific donor\'s rules on budget flexibility, most donors allow some variance between budget lines without formal approval, but exceeding a defined threshold (often 10%) typically requires a formal budget amendment, covered in the next module.</p><h2>Multi-year and multi-currency considerations</h2><p>For multi-year projects, account for inflation and exchange rate risk explicitly in your budget assumptions, and document your exchange rate methodology clearly, since exchange rate fluctuations are a common source of later reporting discrepancies.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Grants Management and Compliance',
                    'lessons' => [
                        [
                            'title' => 'Managing grant budgets and amendments',
                            'minutes' => 50,
                            'content' => '<h2>Ongoing budget monitoring</h2><p>Grant budget management is a continuous process, not a one-time exercise at project start. Track actual expenditure against budget regularly (at minimum monthly) so variances are identified while there is still time to adjust, rather than discovered at year-end reporting.</p><h2>When an amendment is needed</h2><p>Most donors require formal approval for changes exceeding a defined threshold, reallocating funds between major budget categories, extending the project timeline, or changing project scope. Identify the need for an amendment as early as possible, since donor approval processes often take weeks.</p><h2>Preparing a strong amendment request</h2><p>A well-prepared amendment request clearly explains the reason for the change, its impact on project outcomes, and confirms the request stays within the total approved budget where possible, vague or late amendment requests are far more likely to face donor pushback.</p><h2>Documenting amendment history</h2><p>Maintain a clear record of all approved amendments and their justifications, this documentation is frequently requested during audits and is far easier to produce contemporaneously than reconstructed after the fact.</p>',
                        ],
                        [
                            'title' => 'Compliance and eligibility of costs',
                            'minutes' => 45,
                            'content' => '<h2>What "eligible cost" means</h2><p>Not every legitimate organisational expense is an eligible cost under a given grant, donors define specific rules about what can be charged to their funding, and charging an ineligible cost, even unintentionally, can result in it being disallowed and required to be repaid.</p><h2>Common eligibility pitfalls</h2><p>Frequent problem areas include costs incurred outside the approved project period, costs exceeding donor-specified caps (such as per diem rates), and costs lacking adequate supporting documentation, even if the expenditure itself was legitimate and project-related.</p><h2>Building compliance into everyday processes</h2><p>Rather than treating compliance as a separate check performed later, embed eligibility checks into routine procurement and expenditure approval processes, so ineligible costs are caught before they are incurred, not discovered during an audit.</p><h2>Staff training and awareness</h2><p>Compliance failures often stem from staff simply not knowing a specific donor\'s rules, particularly on projects with multiple co-funders each with different requirements. Regular, donor-specific compliance briefings for project and finance staff meaningfully reduce this risk.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Auditing and Internal Controls',
                    'lessons' => [
                        [
                            'title' => 'Preparing for donor and statutory audits',
                            'minutes' => 50,
                            'content' => '<h2>Audit readiness as an ongoing state, not an event</h2><p>Organisations that treat audit preparation as a scramble immediately before an audit visit consistently perform worse than those that maintain audit-ready documentation continuously throughout the project, this lesson focuses on building the latter habit.</p><h2>What auditors typically examine</h2><p>Expect auditors to review supporting documentation for a sample of transactions, verify expenditure matches approved budget lines and eligible cost rules, and check that internal approval processes were actually followed, not just documented as policy.</p><h2>Organising documentation for easy retrieval</h2><p>Maintain a clear, consistent filing system (physical or digital) for all financial supporting documents, organised so any transaction can be traced back to its supporting evidence within minutes, not hours, when an auditor requests it.</p><h2>Managing the audit process itself</h2><p>Designate a single point of contact to coordinate audit requests, respond to findings promptly and professionally, and treat audit findings, even critical ones, as an opportunity to strengthen controls rather than purely a compliance burden to survive.</p>',
                        ],
                        [
                            'title' => 'Strengthening internal financial controls',
                            'minutes' => 45,
                            'content' => '<h2>What internal controls actually protect against</h2><p>Internal controls protect against both error (honest mistakes in recording or processing transactions) and fraud (deliberate misuse of funds), a distinction worth keeping in mind since the appropriate control differs depending on which risk you are addressing.</p><h2>Segregation of duties</h2><p>A foundational control principle: the person who approves a transaction should not be the same person who processes payment or reconciles accounts. Even small organisations should look for ways to separate these roles, even informally, rather than concentrating all financial authority in one person.</p><h2>Approval thresholds and authorisation limits</h2><p>Establish clear, documented spending authorisation limits by role, with higher-value transactions requiring additional levels of approval, and ensure these limits are actually followed in practice, not just written in a policy document that goes unenforced.</p><h2>Regular reconciliation and review</h2><p>Routine practices, monthly bank reconciliation, periodic spot-checks of transactions against supporting documents, surface control weaknesses and errors early, well before they compound into a larger problem discovered only at audit time.</p>',
                        ],
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
                        [
                            'title' => 'Warehouse layout and safety',
                            'minutes' => 40,
                            'content' => '<h2>Why layout is a control, not just convenience</h2><p>A well-planned warehouse layout is a form of inventory control in itself, clear zones, sensible flow from receiving to storage to dispatch, and unambiguous location labelling all reduce the errors and delays that lead to lost stock and safety incidents.</p><h2>Core layout principles</h2><p>Organise the warehouse into clear functional zones, receiving, bulk storage, picking/dispatch, and keep high-turnover items closer to the dispatch area to minimise unnecessary internal movement. Ensure aisles are wide enough for safe equipment and foot traffic simultaneously.</p><h2>Essential safety practices</h2><p>Maintain clear emergency exits and fire equipment access at all times, enforce safe stacking height limits appropriate to your racking and flooring, and ensure any equipment operators (forklifts, pallet jacks) are properly trained and certified.</p><h2>Labelling and location systems</h2><p>Implement a clear, consistent location-coding system (zone, aisle, shelf, bin) so any item can be found, and any empty space identified for new stock, without relying on any one person\'s memory of "where things usually go."</p>',
                        ],
                        [
                            'title' => 'Receiving and dispatching stock',
                            'minutes' => 40,
                            'content' => '<h2>The receiving process</h2><p>Every incoming delivery should be checked against its accompanying documentation (purchase order or delivery note) for quantity and condition before being accepted into inventory, discrepancies caught at receiving are far easier to resolve with the supplier than discrepancies discovered weeks later.</p><h2>Documenting receipt accurately</h2><p>Record every receipt with date, quantity, condition, and any discrepancies noted, and ensure this is reflected in your stock records (covered in the next module) immediately, not batched up and entered days later when details may be forgotten.</p><h2>The dispatch process</h2><p>Dispatches should be authorised against a valid request or order, picked accurately against that documentation, and recorded at the point of dispatch, mirroring the same discipline applied to receiving.</p><h2>Common errors and how to prevent them</h2><p>Frequent dispatch errors include picking the wrong item or quantity, and dispatching without proper authorisation. A simple two-person check (picker and a separate verifier) for high-value or high-risk dispatches meaningfully reduces these errors at low cost.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Inventory and Stock Control',
                    'lessons' => [
                        [
                            'title' => 'Stock cards and inventory records',
                            'minutes' => 40,
                            'content' => '<h2>What a stock card tracks</h2><p>A stock card (physical or digital) is a running record for a single item showing every receipt, dispatch and adjustment, along with a running balance, it is the core record that should always reflect what is physically on the shelf.</p><h2>Maintaining accuracy in real time</h2><p>Update stock cards at the moment a transaction occurs, not in a periodic batch, a delay between a physical stock movement and its recording is one of the most common causes of inventory records drifting out of sync with reality.</p><h2>Physical vs system counts</h2><p>Whether using physical stock cards or a digital inventory system, the recorded balance is only as trustworthy as the discipline behind recording every single movement, a single missed entry compounds into a permanent discrepancy until the next physical count corrects it.</p><h2>The role of periodic stock counts</h2><p>Regular physical counts (cycle counts for high-value items more frequently, full counts periodically) verify that recorded balances match physical reality, and any discrepancy found should be investigated, not just silently adjusted away.</p>',
                        ],
                        [
                            'title' => 'Stock control methods and loss prevention',
                            'minutes' => 45,
                            'content' => '<h2>Stock control methodologies</h2><p>Common approaches include FIFO (First In, First Out, especially important for perishable or expiry-dated items) and setting minimum/maximum stock levels per item to trigger reordering before stockouts occur, without over-ordering and tying up resources in excess inventory.</p><h2>Understanding shrinkage</h2><p>Stock loss ("shrinkage") can result from theft, damage, spoilage, or simple recording error, distinguishing between these causes matters because the appropriate prevention response differs significantly for each.</p><h2>Practical loss-prevention measures</h2><p>Restrict physical access to storage areas to authorised personnel, maintain segregation of duties between those who record stock and those who physically handle it (echoing the same control principle used in financial management), and investigate discrepancies promptly rather than accepting them as routine.</p><h2>Building a loss-prevention culture</h2><p>Beyond specific controls, a culture where staff understand why stock accuracy matters, and feel able to report discrepancies without fear of blame, catches problems earlier than a purely punitive or purely procedural approach alone.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Compliance and Reporting',
                    'lessons' => [
                        [
                            'title' => 'Preparing for warehouse audits',
                            'minutes' => 40,
                            'content' => '<h2>What a warehouse audit examines</h2><p>A warehouse or inventory audit typically verifies that physical stock matches recorded balances, checks that receiving and dispatch documentation is complete and properly authorised, and reviews whether safety and storage standards are being followed.</p><h2>Maintaining audit-ready records continuously</h2><p>As with financial audits, warehouse audit readiness is best maintained continuously, keeping stock cards current, documentation filed and complete, rather than scrambled together only when an audit is announced.</p><h2>Common audit findings</h2><p>Frequent findings include unexplained discrepancies between physical and recorded stock, missing or incomplete receiving/dispatch documentation, and safety non-compliance such as blocked emergency exits or unsafe stacking, each of which is preventable through the practices covered earlier in this course.</p><h2>Responding to audit findings constructively</h2><p>Treat audit findings as a prompt to strengthen specific controls, document corrective actions taken, and follow up to confirm they were actually implemented, not just acknowledged on paper.</p>',
                        ],
                        [
                            'title' => 'Reporting stock movements and status',
                            'minutes' => 35,
                            'content' => '<h2>Why regular reporting matters beyond compliance</h2><p>Beyond satisfying donor or organisational reporting requirements, regular stock movement and status reports give programme managers the visibility they need to plan procurement, anticipate shortages, and demonstrate accountable resource management.</p><h2>Core elements of a stock report</h2><p>An effective periodic report includes opening and closing balances, total receipts and dispatches for the period, current stock levels against minimum/maximum thresholds, and a summary of any discrepancies identified and their resolution status.</p><h2>Flagging items needing attention</h2><p>Highlight items approaching stockout, items near expiry (for perishable or dated stock), and any unresolved discrepancies prominently, rather than burying them in a long undifferentiated list where they are easy to overlook.</p><h2>Keeping reporting sustainable</h2><p>As with other recurring reporting covered elsewhere in this catalogue, build your stock report around a template and a repeatable process from the stock cards and system records you already maintain, rather than requiring special, time-consuming effort each reporting cycle.</p>',
                        ],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Samuel Mwangi', 'role' => 'Finance and Grants Management Consultant'],
        ],
        [
            'slug' => 'cambridge-igcse-mathematics',
            'code' => 'IGCSE-01',
            'category' => 'cambridge-igcse',
            'title' => 'Cambridge IGCSE Mathematics (0580)',
            'tagline' => 'Cover the full syllabus and walk in exam-ready.',
            'shortDescription' => 'A syllabus-aligned course covering number, algebra, geometry, mensuration, trigonometry, statistics and probability for Cambridge IGCSE Mathematics (0580).',
            'introduction' => 'Cambridge IGCSE Mathematics (0580) is assessed across number, algebra, geometry, mensuration, coordinate geometry, trigonometry, and statistics and probability. This course works through each of those syllabus areas in turn, building the fluency and exam technique learners need for both the Core and Extended tiers, with worked reasoning at every step rather than just final answers.',
            'audience' => 'Secondary school learners preparing for the Cambridge IGCSE Mathematics (0580) examination, and independent learners revising the syllabus.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'new',
            'spine' => 'blue',
            'durationWeeks' => 12,
            'price' => 320,
            'originalPrice' => 380,
            'seatsLeft' => 30,
            'nextCohort' => '2026-10-05',
            'mode' => 'online',
            'classification' => 'o_level',
            'certificateKind' => 'recognized',
            'recognizedBody' => 'Cambridge Assessment International Education (CAIE)',
            'objectives' => [
                'Work confidently with number, ratio, percentages and standard form',
                'Manipulate algebraic expressions and solve equations and inequalities',
                'Apply geometric reasoning, mensuration and right-angled trigonometry',
                'Interpret graphs, statistics and probability in exam-style questions',
            ],
            'curriculum' => [
                [
                    'title' => 'Number and Algebra Foundations',
                    'lessons' => [
                        [
                            'title' => 'Number, ratio and percentages',
                            'minutes' => 45,
                            'content' => '<h2>The number toolkit the whole syllabus rests on</h2><p>Almost every Cambridge IGCSE Mathematics question, whether it is about geometry, statistics or algebra, eventually comes down to confident arithmetic with integers, fractions, decimals and directed numbers. This lesson consolidates that foundation: order of operations, working with negative numbers, and converting cleanly between fractions, decimals and percentages.</p><h2>Ratio and proportion</h2><p>Ratio questions ask you to compare quantities in a fixed relationship, while direct and inverse proportion describe how two quantities change together. The key exam skill is translating a worded problem, sharing an amount in a given ratio, scaling a recipe, into the correct number sentence before calculating.</p><h2>Percentages, increase and decrease</h2><p>Beyond calculating a percentage of an amount, the syllabus expects percentage increase and decrease, reverse percentage problems (working backwards from a final amount to an original one), and compound interest calculated over several periods.</p><h2>Standard form</h2><p>Standard form (scientific notation) writes very large or very small numbers as <code>a &times; 10^n</code> with 1 &le; a &lt; 10. Practise converting in both directions and performing calculations directly in standard form, since exam questions often require an answer in this form specifically.</p>',
                        ],
                        [
                            'title' => 'Algebraic manipulation and equations',
                            'minutes' => 50,
                            'content' => '<h2>Expanding and factorising</h2><p>Algebraic manipulation is tested throughout the paper, not just in dedicated algebra questions. This lesson covers expanding single and double brackets, and factorising by taking out a common factor, by grouping, and factorising quadratic expressions of the form <code>x^2 + bx + c</code> and <code>ax^2 + bx + c</code>.</p><h2>Solving linear and quadratic equations</h2><p>Linear equations are solved by systematically isolating the unknown, including equations with brackets or the unknown on both sides. Quadratic equations can be solved by factorising, completing the square, or the quadratic formula, extended-tier learners should be comfortable choosing whichever method is fastest for a given equation.</p><h2>Simultaneous equations</h2><p>Two linear equations in two unknowns are solved by elimination or substitution. Extended-tier papers also expect one linear and one quadratic equation solved simultaneously by substitution, a common source of lost marks if the substitution is not set up carefully.</p><h2>Inequalities</h2><p>Linear inequalities follow the same rules as equations, with one crucial exception: multiplying or dividing by a negative number reverses the inequality sign. Practise representing solution sets on a number line and, for two variables, as a shaded region on a graph.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Geometry, Mensuration and Trigonometry',
                    'lessons' => [
                        [
                            'title' => 'Properties of shapes and geometrical reasoning',
                            'minutes' => 45,
                            'content' => '<h2>Angle facts as building blocks</h2><p>Every geometrical reasoning question is built from a small set of angle facts: angles on a straight line sum to 180&deg;, angles at a point sum to 360&deg;, and angles in a triangle sum to 180&deg;. Alongside these, parallel-line angle facts (corresponding, alternate and co-interior angles) let you find unknown angles in almost any diagram.</p><h2>Polygons and circle theorems</h2><p>The sum of interior angles of a polygon with n sides is <code>(n-2) &times; 180&deg;</code>, and exterior angles of any convex polygon sum to 360&deg;. Extended-tier learners also need the circle theorems, such as the angle at the centre being twice the angle at the circumference, and the angle in a semicircle being 90&deg;.</p><h2>Congruence and similarity</h2><p>Two shapes are congruent if they are identical in shape and size, and similar if one is an enlargement of the other. Similar shapes have equal corresponding angles and proportional corresponding sides, which is the basis for solving many exam problems involving overlapping or nested triangles.</p><h2>Showing your reasoning</h2><p>Geometrical reasoning questions award marks for stated reasons ("angles on a straight line", "alternate angles are equal"), not just the correct numerical answer. Get in the habit of naming the angle fact used at every step.</p>',
                        ],
                        [
                            'title' => 'Mensuration and right-angled trigonometry',
                            'minutes' => 50,
                            'content' => '<h2>Perimeter, area and volume</h2><p>This lesson consolidates the mensuration formulae for the syllabus: area and circumference of a circle, area of a triangle, parallelogram and trapezium, and volume and surface area of prisms, cylinders, pyramids, cones and spheres. Extended-tier questions frequently combine two or more shapes into a single compound solid.</p><h2>Arc length and sector area</h2><p>An arc length or sector area is a fraction of the full circumference or area, found using the fraction <code>&theta;/360</code> of the central angle. Keep the angle and radius clearly labelled on a sketch before substituting into the formula, this single habit prevents most sign and substitution errors.</p><h2>Pythagoras\' theorem</h2><p>In any right-angled triangle, <code>a^2 + b^2 = c^2</code> where c is the hypotenuse. This underpins distance calculations on coordinate grids as well as pure geometry problems, and is often combined with trigonometry in the same question.</p><h2>Right-angled trigonometry</h2><p>Sine, cosine and tangent relate an angle in a right-angled triangle to the ratio of two sides (SOH-CAHTOA). Extended-tier learners extend this to the sine rule, cosine rule and area of a triangle formula for non-right-angled triangles, and to 3D problems where the right angle must first be identified within a 3D solid.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Graphs, Statistics and Probability',
                    'lessons' => [
                        [
                            'title' => 'Coordinate geometry and graphs of functions',
                            'minutes' => 45,
                            'content' => '<h2>Straight-line graphs</h2><p>The equation <code>y = mx + c</code> describes a straight line with gradient m and y-intercept c. From two coordinates you should be able to find the gradient, the midpoint, and the length of the line segment between them (using Pythagoras), and from there derive the full equation of the line.</p><h2>Quadratic and other curved graphs</h2><p>Quadratic graphs form a parabola; learners should be able to plot one from a table of values, identify its turning point and roots, and relate the graph back to factorised or completed-square forms of the equation. The syllabus also covers cubic, reciprocal and exponential graphs at a recognition and sketching level.</p><h2>Reading and interpreting graphs</h2><p>Many exam marks come from reading a given graph accurately: finding a gradient at a point, solving an equation by reading where a curve crosses a line, or interpreting a distance-time or speed-time graph, where the gradient represents speed or acceleration respectively.</p><h2>Transformations of graphs</h2><p>Extended-tier learners should recognise how changes to a function\'s equation, such as <code>f(x) + a</code> or <code>f(x - a)</code>, translate the graph vertically or horizontally, without needing to re-plot every point from scratch.</p>',
                        ],
                        [
                            'title' => 'Statistics and probability for the exam',
                            'minutes' => 45,
                            'content' => '<h2>Summarising data</h2><p>The syllabus expects fluency with mean, median, mode and range, and with grouped frequency data, estimating the mean and identifying the modal and median classes. Learners should also be able to draw and interpret frequency tables, pie charts, bar charts and, for extended tier, histograms with unequal class widths.</p><h2>Cumulative frequency</h2><p>A cumulative frequency diagram lets you read off the median and quartiles for grouped data, and calculate the interquartile range, a common source of exam marks that depends entirely on plotting the cumulative totals against the upper class boundaries correctly.</p><h2>Probability rules</h2><p>Basic probability is the ratio of favourable to total outcomes. The addition rule applies to mutually exclusive events ("or"), and the multiplication rule to independent events ("and"). Tree diagrams make multi-stage probability problems, especially those without replacement, far less error-prone than working from a description alone.</p><h2>Exam technique for data questions</h2><p>Always state which measure of average or spread you are using and why, and double-check that a probability answer sits between 0 and 1. These verification habits catch a large share of avoidable slips under exam time pressure.</p>',
                        ],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Irene Achieng', 'role' => 'Cambridge IGCSE Mathematics Teacher'],
        ],
        [
            'slug' => 'cambridge-igcse-physics',
            'code' => 'IGCSE-02',
            'category' => 'cambridge-igcse',
            'title' => 'Cambridge IGCSE Physics (0625)',
            'tagline' => 'Build a rigorous, exam-ready grasp of physics.',
            'shortDescription' => 'A syllabus-aligned course covering motion and forces, thermal physics and waves, and electricity, magnetism and atomic physics for Cambridge IGCSE Physics (0625).',
            'introduction' => 'Cambridge IGCSE Physics (0625) asks learners to explain everyday phenomena using a fairly small set of core principles, applied consistently. This course works through motion and forces, thermal physics and waves, and electricity, magnetism and atomic physics, with an emphasis on the practical, calculation and definition skills examiners test most often.',
            'audience' => 'Secondary school learners preparing for the Cambridge IGCSE Physics (0625) examination, and independent learners revising the syllabus.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'new',
            'spine' => 'black',
            'durationWeeks' => 10,
            'price' => 340,
            'originalPrice' => 400,
            'seatsLeft' => 26,
            'nextCohort' => '2026-10-05',
            'mode' => 'hybrid',
            'classification' => 'o_level',
            'certificateKind' => 'recognized',
            'recognizedBody' => 'Cambridge Assessment International Education (CAIE)',
            'objectives' => [
                'Describe and calculate motion, forces, and energy transfers',
                'Explain thermal properties, heat transfer and wave behaviour',
                'Analyse electrical circuits and magnetic effects of current',
                'Describe atomic structure and radioactive decay',
            ],
            'curriculum' => [
                [
                    'title' => 'Motion, Forces and Energy',
                    'lessons' => [
                        [
                            'title' => 'Describing motion and Newton\'s laws',
                            'minutes' => 45,
                            'content' => '<h2>Speed, velocity and acceleration</h2><p>Speed is distance travelled per unit time; velocity adds direction; acceleration is the rate of change of velocity. Distance-time graphs have gradient equal to speed, and speed-time graphs have gradient equal to acceleration, with the area under a speed-time graph equal to distance travelled, three facts that unlock most motion-graph questions.</p><h2>Newton\'s first and second laws</h2><p>An object continues at constant velocity (including staying at rest) unless acted on by a resultant force, this is Newton\'s first law. Newton\'s second law quantifies what happens when there is a resultant force: <code>F = ma</code>, force equals mass times acceleration, so the same force produces less acceleration in a more massive object.</p><h2>Newton\'s third law and everyday forces</h2><p>For every force one object exerts on a second, the second exerts an equal and opposite force back. Combine this with friction, air resistance, weight and normal contact force to explain why a falling object reaches terminal velocity once resistive forces balance its weight.</p><h2>Momentum</h2><p>Momentum (mass &times; velocity) is conserved in collisions and explosions when no external force acts. Extended-tier learners should be able to apply conservation of momentum to calculate an unknown velocity before or after a collision.</p>',
                        ],
                        [
                            'title' => 'Energy, work and power',
                            'minutes' => 45,
                            'content' => '<h2>Energy stores and transfers</h2><p>Physics describes energy as being transferred between stores, kinetic, gravitational potential, elastic, thermal, chemical and others, rather than created or destroyed. Being able to name the stores involved before and after a process is often worth as many marks as any calculation.</p><h2>Calculating kinetic and gravitational potential energy</h2><p>Kinetic energy is <code>&frac12;mv^2</code> and gravitational potential energy is <code>mgh</code>. These two formulae, combined with conservation of energy, let you solve a large share of mechanics problems, such as finding the speed of an object at the bottom of a slope from its height at the top.</p><h2>Work done and power</h2><p>Work done equals force multiplied by distance moved in the direction of the force, and is one way energy is transferred. Power is the rate of doing work, energy transferred per second, measured in watts. A common exam pattern gives a time and asks for power, or vice versa.</p><h2>Efficiency</h2><p>No real energy transfer is perfectly efficient; some energy is always dissipated, usually as heat. Efficiency is calculated as useful energy output divided by total energy input, expressed as a percentage, and is frequently tested using Sankey diagrams that show the relative size of each energy transfer.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Thermal Physics and Waves',
                    'lessons' => [
                        [
                            'title' => 'Thermal properties and heat transfer',
                            'minutes' => 40,
                            'content' => '<h2>The kinetic particle model</h2><p>Solids, liquids and gases are explained by how their particles are arranged and how much energy they have: solids have particles vibrating in fixed positions, liquids have particles that can move past each other, and gases have particles moving freely and rapidly. Temperature is a measure of the average kinetic energy of these particles.</p><h2>Specific heat capacity and change of state</h2><p>Specific heat capacity describes how much energy is needed to raise the temperature of a given mass of a substance by one degree. During a change of state, temperature stays constant even as energy continues to be supplied, since that energy is instead breaking or forming bonds between particles (specific latent heat).</p><h2>Conduction, convection and radiation</h2><p>Conduction transfers heat through vibrating particles and, in metals, free electrons, and works best in solids. Convection relies on density differences in fluids that can flow. Radiation transfers heat as electromagnetic waves and needs no medium at all, which is why it is the only method that transfers heat through a vacuum.</p><h2>Applying the three methods</h2><p>Exam questions often describe a real object, a vacuum flask, a car radiator, and ask which transfer methods matter and how the design reduces or encourages them. Practise identifying all three methods operating in a single scenario.</p>',
                        ],
                        [
                            'title' => 'Properties of waves, light and sound',
                            'minutes' => 45,
                            'content' => '<h2>Describing waves</h2><p>Every wave can be described by its amplitude, wavelength, frequency and speed, related by <code>speed = frequency &times; wavelength</code>. Transverse waves (like light) vibrate perpendicular to the direction of travel; longitudinal waves (like sound) vibrate parallel to it.</p><h2>Reflection and refraction of light</h2><p>Light reflects off a surface with the angle of incidence equal to the angle of reflection, and refracts (bends) when passing between materials of different density, slowing down and bending towards the normal when entering a denser material. Total internal reflection occurs beyond a critical angle and underlies how optical fibres work.</p><h2>The electromagnetic spectrum</h2><p>All electromagnetic waves travel at the same speed in a vacuum but differ in wavelength and frequency, from radio waves through to gamma rays. Learners should know the broad order of the spectrum and at least one practical use and one hazard associated with several of its regions.</p><h2>Sound waves</h2><p>Sound is a longitudinal wave that requires a medium and cannot travel through a vacuum, a common point of confusion with light. Pitch relates to frequency and loudness to amplitude, and the speed of sound can be measured using an echo over a known distance.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Electricity, Magnetism and Atomic Physics',
                    'lessons' => [
                        [
                            'title' => 'Electrical circuits and magnetism',
                            'minutes' => 50,
                            'content' => '<h2>Current, voltage and resistance</h2><p>Current is the rate of flow of charge, voltage is the energy transferred per unit charge, and resistance opposes current flow, related by <code>V = IR</code>. Building confidence with this single equation, and rearranging it correctly, resolves most circuit calculation questions.</p><h2>Series and parallel circuits</h2><p>In series, current is the same everywhere and voltages share across components; in parallel, voltage is the same across each branch and current shares between branches. Total resistance behaves oppositely too: resistances add directly in series, but combine to give a smaller total resistance in parallel.</p><h2>Electrical power and cost</h2><p>Electrical power is <code>P = IV</code>, and combined with <code>V = IR</code> gives two further useful forms, <code>P = I^2R</code> and <code>P = V^2/R</code>. These let you choose the most convenient equation depending on which quantities a question actually gives you.</p><h2>Magnetic effects of current</h2><p>A current-carrying wire produces a magnetic field around it, which is the basis of the electromagnet, and can experience a force when placed in an external magnetic field, the basis of the electric motor. Learners should be able to predict field direction and force direction using the appropriate right-hand or left-hand rule.</p>',
                        ],
                        [
                            'title' => 'Atomic structure and nuclear physics',
                            'minutes' => 45,
                            'content' => '<h2>Inside the atom</h2><p>An atom has a small, dense, positively charged nucleus containing protons and neutrons, surrounded by orbiting electrons. The number of protons (atomic number) defines the element, while the number of neutrons can vary between isotopes of the same element.</p><h2>Radioactive decay</h2><p>Unstable nuclei emit alpha, beta or gamma radiation to become more stable. Alpha particles are the most ionising but least penetrating (stopped by paper), beta particles are intermediate (stopped by a few millimetres of aluminium), and gamma rays are the least ionising but most penetrating, requiring thick lead or concrete to stop.</p><h2>Half-life</h2><p>Half-life is the time taken for half of the radioactive nuclei in a sample to decay, a constant property of a given isotope regardless of the current sample size. Exam questions typically give an initial count rate and ask how many half-lives have passed, or the reverse.</p><h2>Safe and responsible use</h2><p>Radioactive sources have genuine medical, industrial and power-generation uses, but require careful handling, shielding, distance and minimising exposure time, which examiners often expect you to state explicitly when asked about safety precautions.</p>',
                        ],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Brian Otieno', 'role' => 'Cambridge IGCSE Physics Teacher'],
        ],
        [
            'slug' => 'cambridge-igcse-biology',
            'code' => 'IGCSE-03',
            'category' => 'cambridge-igcse',
            'title' => 'Cambridge IGCSE Biology (0610)',
            'tagline' => 'Understand life processes from cell to ecosystem.',
            'shortDescription' => 'A syllabus-aligned course covering cell biology, human systems and reproduction, and genetics and ecology for Cambridge IGCSE Biology (0610).',
            'introduction' => 'Cambridge IGCSE Biology (0610) builds from the structure of a single cell up to whole ecosystems. This course follows that same progression, covering cell biology and life processes, human body systems and reproduction, and genetics, evolution and ecology, with clear, jargon-free explanations of processes examiners frequently ask learners to describe or explain in their own words.',
            'audience' => 'Secondary school learners preparing for the Cambridge IGCSE Biology (0610) examination, and independent learners revising the syllabus.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'new',
            'spine' => 'green',
            'durationWeeks' => 10,
            'price' => 340,
            'originalPrice' => 400,
            'seatsLeft' => 28,
            'nextCohort' => '2026-10-12',
            'mode' => 'online',
            'classification' => 'o_level',
            'certificateKind' => 'recognized',
            'recognizedBody' => 'Cambridge Assessment International Education (CAIE)',
            'objectives' => [
                'Explain cell structure, transport and nutrition in living organisms',
                'Describe key human body systems and how they maintain health',
                'Explain inheritance, variation and evolution by natural selection',
                'Analyse how organisms interact within ecosystems and with humans',
            ],
            'curriculum' => [
                [
                    'title' => 'Cell Biology and Life Processes',
                    'lessons' => [
                        [
                            'title' => 'Cell structure and movement of substances',
                            'minutes' => 45,
                            'content' => '<h2>Plant and animal cells compared</h2><p>Both plant and animal cells share a nucleus, cytoplasm and cell membrane, but plant cells additionally have a cell wall, chloroplasts and a large permanent vacuole. Being able to label and explain the function of each structure is a recurring exam requirement, not just a diagram-labelling exercise.</p><h2>Specialised cells</h2><p>Cells are adapted to their function: a red blood cell has no nucleus to maximise space for oxygen-carrying haemoglobin, a root hair cell has a large surface area to absorb water and minerals, and a neurone is elongated to carry electrical impulses over distance. Exam questions often ask you to link a structural feature directly to the function it enables.</p><h2>Diffusion, osmosis and active transport</h2><p>Diffusion is the net movement of particles from a region of higher to lower concentration, requiring no energy. Osmosis is a special case of diffusion specifically for water across a partially permeable membrane. Active transport moves substances against a concentration gradient and, unlike the other two, requires energy from respiration.</p><h2>Why this distinction matters</h2><p>A common exam trap is describing active transport as diffusion because both involve movement of particles. Always check whether movement is with or against the concentration gradient, and whether energy is required, before naming the process.</p>',
                        ],
                        [
                            'title' => 'Nutrition and the human digestive system',
                            'minutes' => 45,
                            'content' => '<h2>Nutrient groups and their roles</h2><p>Carbohydrates and fats primarily provide energy, proteins provide the building blocks for growth and repair, and vitamins and minerals support specific body functions in small quantities. A balanced diet supplies each in appropriate proportion for a person\'s age, activity level and health.</p><h2>Digestion as a physical and chemical process</h2><p>Physical digestion (chewing, churning in the stomach) increases surface area for chemical digestion, where enzymes break large insoluble molecules into small soluble ones that can be absorbed. Each enzyme, amylase, protease and lipase, is specific to one type of nutrient.</p><h2>The role of enzymes</h2><p>Digestive enzymes work fastest at an optimum temperature and pH; conditions too far from this optimum reduce their activity, and extreme conditions denature them permanently. This is why the stomach\'s highly acidic environment suits pepsin, while pancreatic enzymes in the small intestine work in a more alkaline environment.</p><h2>Absorption in the small intestine</h2><p>The small intestine is adapted for absorption with villi and microvilli that dramatically increase surface area, a thin wall for short diffusion distance, and a rich blood supply that maintains a steep concentration gradient, all three features reinforcing each other.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Human Systems and Reproduction',
                    'lessons' => [
                        [
                            'title' => 'Transport, gas exchange and respiration',
                            'minutes' => 45,
                            'content' => '<h2>The circulatory system as a transport network</h2><p>The heart pumps blood through a double circulatory system, one loop to the lungs and back, one loop to the rest of the body, which keeps oxygenated and deoxygenated blood mostly separate and allows blood to reach the body at higher pressure than a single loop would allow.</p><h2>Blood vessels compared</h2><p>Arteries carry blood away from the heart under high pressure and have thick, muscular, elastic walls; veins carry blood back to the heart under low pressure and rely on valves to prevent backflow; capillaries are one cell thick, allowing efficient exchange of substances with surrounding tissue.</p><h2>Gas exchange in the lungs</h2><p>The lungs are adapted for efficient gas exchange with millions of alveoli providing a large surface area, thin alveolar walls for a short diffusion path, and a dense capillary network maintaining a steep concentration gradient for oxygen and carbon dioxide, the same three adaptation principles seen in the small intestine.</p><h2>Aerobic and anaerobic respiration</h2><p>Aerobic respiration uses oxygen to release energy from glucose efficiently, producing carbon dioxide and water. Anaerobic respiration in humans occurs without sufficient oxygen (such as during intense exercise), releases far less energy, and produces lactic acid, which is why it cannot sustain prolonged activity.</p>',
                        ],
                        [
                            'title' => 'Coordination, hormones and reproduction',
                            'minutes' => 45,
                            'content' => '<h2>Nervous versus hormonal coordination</h2><p>The nervous system sends fast, short-lived electrical impulses along neurones for immediate responses, such as reflex actions, while the hormonal system sends slower, longer-lasting chemical messages through the bloodstream, better suited to processes like growth or the menstrual cycle that unfold over hours or days.</p><h2>The reflex arc</h2><p>A reflex action follows a fixed pathway: a stimulus is detected by a receptor, an impulse travels along a sensory neurone to the spinal cord, is relayed to a motor neurone, and triggers an effector (muscle or gland), all without needing conscious brain involvement, which is why reflexes are so fast.</p><h2>Key hormones and their effects</h2><p>Insulin, secreted by the pancreas, lowers blood glucose by stimulating cells to take up glucose from the blood, part of a wider negative feedback system that keeps internal conditions stable. Reproductive hormones such as oestrogen, progesterone, FSH and LH interact to control the menstrual cycle and support pregnancy.</p><h2>Human reproduction</h2><p>The male and female reproductive systems are structured to produce gametes, enable fertilisation, and, in the female, support development of a fetus. Learners should be able to describe the roles of key structures (testes, ovaries, uterus, placenta) rather than only naming them.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Genetics, Evolution and Ecology',
                    'lessons' => [
                        [
                            'title' => 'Inheritance and variation',
                            'minutes' => 50,
                            'content' => '<h2>DNA, genes and chromosomes</h2><p>DNA is a double-helix molecule carrying genetic information as a sequence of bases; a gene is a section of DNA coding for a particular characteristic, and chromosomes are structures within the nucleus that carry many genes together. Human body cells normally contain 23 pairs of chromosomes.</p><h2>Alleles, dominant and recessive</h2><p>Different versions of the same gene are called alleles. A dominant allele\'s characteristic appears whenever it is present, while a recessive allele\'s characteristic only appears when no dominant allele is present, which is why two apparently healthy carrier parents can have a child with a recessive genetic condition.</p><h2>Genetic diagrams</h2><p>Genetic diagrams (Punnett squares) predict the possible genotypes and phenotypes of offspring from parents of known genotype, and the resulting ratios. Setting out the diagram carefully, parent genotypes, gametes, then the offspring grid, is more reliable under exam conditions than trying to reason through probabilities in your head.</p><h2>Sources of variation</h2><p>Variation between individuals arises from genetic differences (inherited, including mutation), environmental differences (not inherited, such as diet or exercise), or a combination of both. Distinguishing which type of variation a scenario describes is a frequently tested skill.</p>',
                        ],
                        [
                            'title' => 'Ecosystems, human impact and the environment',
                            'minutes' => 45,
                            'content' => '<h2>Energy flow through ecosystems</h2><p>Energy enters ecosystems through photosynthesis and flows from producers to primary, secondary and tertiary consumers through food chains and food webs. At each stage, most energy is lost as heat through respiration, movement and undigested material, which is why food chains rarely extend beyond four or five levels.</p><h2>Nutrient cycles</h2><p>The carbon cycle and nitrogen cycle describe how essential elements move between living organisms and the physical environment. Decomposers play a central role in both, breaking down dead organic matter and waste to release nutrients back into the soil or atmosphere for reuse.</p><h2>Human impact on ecosystems</h2><p>Human activity, deforestation, pollution, overfishing and the greenhouse effect, disrupts these natural cycles and reduces biodiversity. Learners should be able to explain the specific mechanism of impact for a given example, not just state that an activity is "bad for the environment".</p><h2>Conservation approaches</h2><p>Conservation strategies range from protected areas and captive breeding programmes to sustainable resource management practices. Effective answers connect a named strategy to the specific problem it addresses, rather than listing conservation methods generically.</p>',
                        ],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Grace Njoroge', 'role' => 'Cambridge IGCSE Biology Teacher'],
        ],
        [
            'slug' => 'cambridge-igcse-business-studies',
            'code' => 'IGCSE-04',
            'category' => 'cambridge-igcse',
            'title' => 'Cambridge IGCSE Business Studies (0450)',
            'tagline' => 'Learn how real businesses are organised and run.',
            'shortDescription' => 'A syllabus-aligned course covering business activity, people and marketing, and finance for Cambridge IGCSE Business Studies (0450).',
            'introduction' => 'Cambridge IGCSE Business Studies (0450) develops an understanding of how businesses are organised, managed and financed, and of the wider environment they operate in. This course covers business activity and organisation, people and marketing, and finance and external influences, using the case-study style questions the exam relies on throughout.',
            'audience' => 'Secondary school learners preparing for the Cambridge IGCSE Business Studies (0450) examination, and independent learners revising the syllabus.',
            'image' => 'full-stack-web-development.jpg',
            'level' => 'beginner',
            'tag' => 'new',
            'spine' => 'bright',
            'durationWeeks' => 8,
            'price' => 300,
            'originalPrice' => null,
            'seatsLeft' => 32,
            'nextCohort' => '2026-10-12',
            'mode' => 'hybrid',
            'classification' => 'o_level',
            'certificateKind' => 'recognized',
            'recognizedBody' => 'Cambridge Assessment International Education (CAIE)',
            'objectives' => [
                'Explain why businesses exist and how they are organised',
                'Describe how businesses manage and motivate people',
                'Apply the marketing mix to real business scenarios',
                'Interpret business finance and explain external influences on business',
            ],
            'curriculum' => [
                [
                    'title' => 'Business Activity and Organisation',
                    'lessons' => [
                        [
                            'title' => 'Understanding business activity',
                            'minutes' => 40,
                            'content' => '<h2>Why businesses exist</h2><p>Businesses combine the factors of production, land, labour, capital and enterprise, to produce goods and services that satisfy customer needs and wants, while pursuing objectives such as profit, growth or survival. Every case study in this subject starts from identifying what need a business is actually meeting.</p><h2>The private and public sectors</h2><p>Private sector businesses are owned and run by individuals or groups aiming primarily for profit, while public sector organisations are owned and funded by government to provide services for the wider public benefit. Many economies also have a growing not-for-profit sector pursuing social rather than purely commercial goals.</p><h2>Business objectives</h2><p>Objectives commonly include survival (especially for new businesses), profit maximisation, growth, market share and increasingly social or environmental responsibility. Objectives can conflict, for example rapid growth can strain the cash a business needs to survive, which is a common theme in exam case studies.</p><h2>Stakeholders</h2><p>Stakeholders, owners, employees, customers, suppliers, government and the local community, each have different, sometimes conflicting interests in a business\'s decisions. Strong exam answers identify specific stakeholders affected by a scenario and explain the impact on each, rather than referring to "stakeholders" in general.</p>',
                        ],
                        [
                            'title' => 'Organisation, structure and management',
                            'minutes' => 45,
                            'content' => '<h2>Forms of business organisation</h2><p>Sole traders are owned and controlled by one person with unlimited liability; partnerships share ownership among two or more people; and limited companies (private or public) have shareholders with limited liability, protecting personal assets beyond their investment if the business fails.</p><h2>Choosing a legal structure</h2><p>The choice between these structures involves trade-offs between ease of setting up, access to capital, control, and liability. A sole trader keeps full control but bears full risk personally; forming a limited company can raise more capital and share risk, but comes with more legal requirements and shared control.</p><h2>Organisational structure and span of control</h2><p>An organisational chart shows the formal hierarchy, chain of command and span of control (how many people a manager directly supervises) within a business. A wide span of control with fewer management layers can speed up communication but may reduce direct supervision of each employee.</p><h2>Leadership and management styles</h2><p>Autocratic leaders make decisions unilaterally, democratic leaders involve employees in decision-making, and laissez-faire leaders delegate heavily and intervene minimally. The most effective style depends on the situation, workforce skill and time pressure, not one style being universally best.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'People and Marketing',
                    'lessons' => [
                        [
                            'title' => 'Motivation, recruitment and people in business',
                            'minutes' => 40,
                            'content' => '<h2>Why motivation matters</h2><p>Motivated employees are typically more productive, produce higher-quality work and are less likely to leave, reducing recruitment and training costs. Motivation theories, such as Maslow\'s hierarchy of needs and Herzberg\'s two-factor theory, offer different explanations for what drives people at work.</p><h2>Financial and non-financial motivators</h2><p>Financial methods include wages, salaries, commission, bonuses and profit-sharing. Non-financial methods include job enrichment, training, promotion opportunities and improved working conditions. Effective case-study answers match a specific motivator to the specific problem described, rather than suggesting "pay them more" as a universal fix.</p><h2>The recruitment process</h2><p>Recruitment typically follows job analysis, drafting a job description and person specification, advertising internally or externally, shortlisting, interviewing and selecting. Internal recruitment is usually faster and cheaper; external recruitment brings in new skills and perspectives.</p><h2>Training and its costs</h2><p>Induction training orients new employees, on-the-job training develops skills in the actual work environment at lower cost, and off-the-job training (courses, workshops) can teach broader skills but at higher cost and with output lost while staff are away from work.</p>',
                        ],
                        [
                            'title' => 'Marketing and the marketing mix',
                            'minutes' => 45,
                            'content' => '<h2>Market research: quantitative and qualitative</h2><p>Quantitative research produces numerical data (surveys, sales figures) that is easy to analyse and compare, while qualitative research (focus groups, interviews) explores opinions and motivations in depth but is harder to generalise from. Primary research is collected first-hand for a specific purpose; secondary research reuses existing data collected by someone else.</p><h2>Market segmentation</h2><p>Businesses segment markets by characteristics such as age, income, location or lifestyle, allowing marketing to be targeted more precisely than a single one-size-fits-all approach, which usually improves both the effectiveness and efficiency of marketing spend.</p><h2>The four Ps</h2><p>The marketing mix, product, price, place and promotion, describes the controllable variables a business combines to market a good or service. A strong exam answer explains how these four elements should be consistent with each other, a premium product needs a matching price and promotional tone, not just described separately.</p><h2>Pricing strategies</h2><p>Common strategies include penetration pricing (low initial price to gain market share), skimming (high initial price for a new, distinctive product), and competitive pricing (matching rivals). The right choice depends on the product\'s novelty, cost structure and competitive environment.</p>',
                        ],
                    ],
                ],
                [
                    'title' => 'Finance and External Influences',
                    'lessons' => [
                        [
                            'title' => 'Business finance and accounts',
                            'minutes' => 45,
                            'content' => '<h2>Sources of finance</h2><p>Internal sources include retained profit and selling assets; external sources include bank loans, overdrafts, share capital and trade credit. Short-term needs (such as cash flow gaps) are usually matched with short-term finance like an overdraft, while long-term investments are matched with long-term finance like a loan or share issue.</p><h2>Cash flow versus profit</h2><p>Cash flow is the movement of money in and out of a business, while profit is revenue minus costs over a period; a business can be profitable on paper yet still run out of cash if payments are timed poorly, one of the most commonly tested distinctions in this subject.</p><h2>Reading a cash flow forecast</h2><p>A cash flow forecast projects expected inflows and outflows to identify future cash shortages before they happen, allowing a business to arrange finance in advance rather than reactively. Exam questions often ask you to identify a specific month\'s shortfall and suggest an appropriate remedy.</p><h2>Statement of comprehensive income and financial position basics</h2><p>The statement of comprehensive income shows revenue, costs and profit over a trading period; the statement of financial position shows what a business owns (assets) and owes (liabilities) at a single point in time. Learners should be able to calculate simple profitability and liquidity ratios from these statements.</p>',
                        ],
                        [
                            'title' => 'External influences on business',
                            'minutes' => 40,
                            'content' => '<h2>Economic influences</h2><p>Inflation, interest rates, unemployment and exchange rates all affect business costs, demand and competitiveness. For example, higher interest rates increase the cost of borrowing and can reduce consumer spending, typically reducing demand for non-essential goods and services.</p><h2>Government and legal influences</h2><p>Government policy affects business through taxation, regulation (such as consumer protection and employment law) and, in some cases, direct support or subsidy. Businesses need to adapt operations to remain compliant as legislation changes, which case studies often frame as a cost or constraint to be managed.</p><h2>Environmental and ethical influences</h2><p>Growing consumer and regulatory attention to environmental and ethical practice means businesses increasingly weigh sustainability alongside profit, whether through sourcing decisions, waste reduction or transparent supply chains, and case studies often ask you to evaluate the trade-off between ethical practice and short-term cost.</p><h2>Globalisation and competition</h2><p>Globalisation has increased both opportunities (larger markets, cheaper inputs) and competitive pressure (international rivals, exchange-rate risk) for businesses of all sizes. Strong evaluative answers weigh both the opportunities and the risks a specific business faces from operating in a more globalised market, rather than treating globalisation as purely positive or negative.</p>',
                        ],
                    ],
                ],
            ],
            'mentor' => ['name' => 'Peter Kamau', 'role' => 'Cambridge IGCSE Business Studies Teacher'],
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
                    'thumbnail_url' => $this->resolveThumbnailUrl($courseData['slug']),
                    'price' => $courseData['price'],
                    'original_price' => $courseData['originalPrice'],
                    'currency' => 'USD',
                    'status' => 'published',
                    'admin_approval_status' => 'approved',
                    'classification' => $courseData['classification'] ?? 'skills_professional',
                    'certificate_kind' => $courseData['certificateKind'] ?? 'completion',
                    'recognized_body' => $courseData['recognizedBody'] ?? null,
                    'level' => $courseData['level'],
                    'tag' => $courseData['tag'],
                    'spine' => $courseData['spine'],
                    'mode' => self::COURSE_MODES[$courseData['mode']],
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
     * Points at the real uploaded thumbnail for this course's slug on the
     * "public" disk (storage/app/public/course-thumbnails) if one exists,
     * otherwise leaves thumbnail_url null so the frontend falls back to its
     * own bundled placeholder image instead of linking a file that 404s.
     */
    private function resolveThumbnailUrl(string $slug): ?string
    {
        $path = "course-thumbnails/{$slug}.jpg";

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
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
                'bio' => "{$mentor['name']} is a {$mentor['role']} and mentor at GISE.",
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
                    'content_url_or_body' => $lessonData['content']
                        ?? "<p>Lesson content for \"{$lessonData['title']}\" will be added by the instructor.</p>",
                    'duration_minutes' => $lessonData['minutes'],
                    'is_preview' => $index === 0,
                ]
            );
        }
    }

    // The catalogue data below still uses the original delivery labels.
    private const COURSE_MODES = ['online' => 'virtual', 'in_person' => 'physical', 'hybrid' => 'both'];

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
                'mode' => $courseData['mode'] === 'online' ? 'virtual' : 'physical',
                'price' => $courseData['price'],
                'location_city' => $courseData['mode'] === 'online' ? null : 'Nairobi',
                'location_country' => $courseData['mode'] === 'online' ? null : 'Kenya',
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
