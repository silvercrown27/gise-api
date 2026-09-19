<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\ModuleQuiz;
use App\Models\ModuleQuizQuestion;
use Illuminate\Database\Seeder;

/**
 * Seeds one end-of-module quiz per course_modules row, keyed by the course
 * slug (from CourseCatalogSeeder) and the module's order_index. A learner
 * must pass a module's quiz before the next module unlocks, enforced by
 * App\Services\ModuleAccessService.
 */
class ModuleQuizSeeder extends Seeder
{
    /**
     * courseSlug => [order_index => ['title', 'questions' => [['q','options'=>['A'=>..],'correct'=>'A'], ...]]]
     */
    private array $quizzes = [
        'gis-and-spatial-analysis-for-agriculture-and-food-security' => [
            0 => [
                'title' => 'Foundations of GIS for Agriculture Quiz',
                'questions' => [
                    ['q' => 'What does GIS stand for?', 'options' => ['A' => 'Geographic Information System', 'B' => 'General Information Software', 'C' => 'Geo Imaging System', 'D' => 'Global Information Standard'], 'correct' => 'A'],
                    ['q' => 'Vector data represents features as points, lines and polygons, while raster data represents:', 'options' => ['A' => 'Only text attributes', 'B' => 'A continuous grid of cells', 'C' => 'Only survey forms', 'D' => 'Administrative boundaries only'], 'correct' => 'B'],
                    ['q' => "Why is checking a layer's coordinate reference system (CRS) important before combining datasets?", 'options' => ['A' => 'It changes the file size', 'B' => 'Mismatched CRS can cause layers to not align spatially', 'C' => "It's only needed for raster data", 'D' => 'It determines the file format'], 'correct' => 'B'],
                ],
            ],
            1 => [
                'title' => 'Spatial Data Collection and Management Quiz',
                'questions' => [
                    ['q' => 'Which of these is a free source of satellite imagery for agricultural monitoring?', 'options' => ['A' => 'Sentinel-2 via the Copernicus Open Access Hub', 'B' => 'A private drone company only', 'C' => 'Google Docs', 'D' => 'A newspaper archive'], 'correct' => 'A'],
                    ['q' => 'A GPS coordinate reading of exactly (0,0) in field data most likely indicates:', 'options' => ['A' => 'A perfectly accurate reading', 'B' => 'A GPS error', 'C' => 'A surveyed equator/prime-meridian intersection', 'D' => 'A raster file'], 'correct' => 'B'],
                    ['q' => 'Ground survey data is most useful for capturing information that satellite imagery cannot show, such as:', 'options' => ['A' => 'Land cover type', 'B' => 'Self-reported household food security status', 'C' => 'General elevation', 'D' => 'Vegetation greenness'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Mapping Food Security and Land Use Quiz',
                'questions' => [
                    ['q' => 'In QGIS, a basic supervised classification works by:', 'options' => ['A' => 'Randomly assigning colours to pixels', 'B' => 'Selecting sample "training" areas of known land cover to classify the rest of the image', 'C' => 'Manually redrawing every pixel', 'D' => 'Deleting all raster layers'], 'correct' => 'B'],
                    ['q' => 'A food security vulnerability index is typically built by:', 'options' => ['A' => 'Using only one data source', 'B' => 'Combining multiple normalised and weighted indicator layers', 'C' => 'Ignoring rainfall data', 'D' => 'Using only population count'], 'correct' => 'B'],
                    ['q' => 'What is the most important principle when designing a map for non-GIS stakeholders?', 'options' => ['A' => 'Include every available data layer', 'B' => 'Keep a clear single message with a plain-language legend', 'C' => 'Use a rainbow colour scheme regardless of context', 'D' => 'Omit the legend to keep it simple'], 'correct' => 'B'],
                ],
            ],
        ],
        'gis-for-natural-resource-management' => [
            0 => [
                'title' => 'GIS Foundations for Natural Resources Quiz',
                'questions' => [
                    ['q' => 'NDVI is primarily used to measure:', 'options' => ['A' => 'Air temperature', 'B' => 'Vegetation health and density', 'C' => 'Soil salinity', 'D' => 'Water pH'], 'correct' => 'B'],
                    ['q' => 'Why is dating each layer clearly (e.g. "forest_cover_2020") important in environmental monitoring?', 'options' => ['A' => "It's a legal requirement", 'B' => 'Monitoring relies on comparing the same area across time periods', 'C' => 'It changes the raster resolution', 'D' => 'It has no real importance'], 'correct' => 'B'],
                    ['q' => 'Which satellite is commonly used for landscape-scale forest monitoring due to its 10m resolution and ~5-day revisit?', 'options' => ['A' => 'Sentinel-2', 'B' => 'A weather balloon', 'C' => 'A geostationary TV satellite', 'D' => 'None, only drones are used'], 'correct' => 'A'],
                ],
            ],
            1 => [
                'title' => 'Monitoring Land, Forest and Water Resources Quiz',
                'questions' => [
                    ['q' => 'A common pitfall in forest-cover change detection is:', 'options' => ['A' => 'Comparing imagery from different seasons, causing false "change"', 'B' => 'Using too much data', 'C' => 'Using only one date of imagery', 'D' => 'Ignoring elevation entirely'], 'correct' => 'A'],
                    ['q' => 'A water catchment (watershed) is defined as:', 'options' => ['A' => 'Any river on a map', 'B' => 'The land area that drains to a single outlet point', 'C' => 'The place with the most rainfall in a country', 'D' => 'A protected forest zone'], 'correct' => 'B'],
                    ['q' => 'Catchment boundaries are most commonly delineated using:', 'options' => ['A' => 'A population census', 'B' => 'A Digital Elevation Model (DEM)', 'C' => 'A single rainfall gauge', 'D' => 'Social media data'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Analysis and Conservation Planning Quiz',
                'questions' => [
                    ['q' => 'Genuine land degradation is best identified by:', 'options' => ['A' => 'A single before/after snapshot', 'B' => 'A sustained multi-year decline in vegetation trend', 'C' => 'One high-resolution photo', 'D' => 'Ignoring rainfall data entirely'], 'correct' => 'B'],
                    ['q' => 'In a weighted-overlay conservation prioritisation approach, criteria like ecological value and threat level are:', 'options' => ['A' => 'Ignored', 'B' => 'Scored and combined into a transparent priority ranking', 'C' => 'Only used for water catchments', 'D' => 'Randomly assigned'], 'correct' => 'B'],
                    ['q' => 'Why should a conservation planning workflow be easy to re-run?', 'options' => ['A' => 'Because new imagery or field data becomes available over time', 'B' => "Because results should change every day for no reason", 'C' => 'It has no benefit', 'D' => 'To make maps less useful'], 'correct' => 'A'],
                ],
            ],
        ],
        'gis-for-disease-surveillance-monitoring' => [
            0 => [
                'title' => 'GIS Foundations for Public Health Quiz',
                'questions' => [
                    ['q' => 'Rate mapping (normalising case counts by population) is preferred over raw case-count mapping because:', 'options' => ['A' => 'Raw counts always look better', 'B' => 'Dense areas naturally have more absolute cases without a higher rate', 'C' => 'Rate mapping is easier to draw', 'D' => 'They are the same thing'], 'correct' => 'B'],
                    ['q' => 'Why does precise, individual case-location mapping raise ethical concerns?', 'options' => ['A' => 'It costs more to produce', 'B' => 'It can risk identifying individuals, especially in small communities', 'C' => "It's technically impossible", 'D' => 'It requires no privacy considerations'], 'correct' => 'B'],
                    ['q' => 'A key first step before mapping health surveillance data is:', 'options' => ['A' => 'Publishing it publicly immediately', 'B' => 'Joining health records to geography using a shared code or facility name', 'C' => 'Deleting incomplete records', 'D' => 'Ignoring the data source'], 'correct' => 'B'],
                ],
            ],
            1 => [
                'title' => 'Mapping Outbreaks and Coverage Quiz',
                'questions' => [
                    ['q' => 'Hotspot statistics such as Getis-Ord Gi* help distinguish:', 'options' => ['A' => 'A statistically significant cluster from cases that coincide with population density', 'B' => 'Choropleth colours', 'C' => 'Data file formats', 'D' => 'Nothing useful'], 'correct' => 'A'],
                    ['q' => 'A straight-line buffer distance for health facility coverage can be misleading because:', 'options' => ['A' => "It's always accurate", 'B' => 'It ignores real roads, terrain and barriers like rivers', 'C' => "It's too complicated to calculate", 'D' => 'It only works for hospitals'], 'correct' => 'B'],
                    ['q' => 'Overlaying population data (e.g. WorldPop) onto a facility service-area layer helps to:', 'options' => ['A' => 'Estimate how many people are inside vs outside effective coverage', 'B' => 'Change the facility locations', 'C' => 'Replace the need for any other analysis', 'D' => 'Calculate rainfall'], 'correct' => 'A'],
                ],
            ],
            2 => [
                'title' => 'Surveillance Dashboards and Reporting Quiz',
                'questions' => [
                    ['q' => 'An effective surveillance dashboard should always display:', 'options' => ['A' => 'Every possible metric with no priority', 'B' => 'A clear "last updated" timestamp alongside key indicators', 'C' => 'Only a map with no numbers', 'D' => 'Historical data older than a year only'], 'correct' => 'B'],
                    ['q' => 'A strong spatial briefing to a response team should:', 'options' => ['A' => 'Lead with the key finding, then show supporting evidence', 'B' => 'Start with technical methodology only', 'C' => 'Avoid stating any recommendation', 'D' => 'Never include a map'], 'correct' => 'A'],
                    ['q' => 'Why is a repeatable data-refresh process important for a dashboard?', 'options' => ['A' => "It isn't important", 'B' => 'A dashboard requiring hours of manual rebuilding tends to stop being updated', 'C' => 'It looks nicer', 'D' => 'It automatically fixes data errors'], 'correct' => 'B'],
                ],
            ],
        ],
        'gis-data-collection-management-analysis-visualization-and-mapping' => [
            0 => [
                'title' => 'Field Data Collection Quiz',
                'questions' => [
                    ['q' => 'The single biggest driver of poor-quality spatial data is:', 'options' => ['A' => 'Starting collection before the data schema/plan is settled', 'B' => 'Using too many surveyors', 'C' => 'Collecting too little data', 'D' => 'Using GPS devices'], 'correct' => 'A'],
                    ['q' => 'Consumer-grade GPS/smartphone accuracy is typically:', 'options' => ['A' => 'Sub-millimetre', 'B' => '3-10m under open sky, worse under canopy', 'C' => 'Always exactly 1km', 'D' => 'Not measurable'], 'correct' => 'B'],
                    ['q' => 'Why should attribute data be captured together with spatial features at the same time in the field?', 'options' => ['A' => 'It saves battery', 'B' => 'Gaps between capture and later linking cause most field data errors', 'C' => 'It has no benefit', 'D' => 'It is required by GPS hardware'], 'correct' => 'B'],
                ],
            ],
            1 => [
                'title' => 'Data Management and Analysis Quiz',
                'questions' => [
                    ['q' => 'Buffering answers which kind of question?', 'options' => ['A' => 'What is within a given distance of a feature', 'B' => 'What is the population of a country', 'C' => 'What is the file format of a layer', 'D' => 'What is the currency exchange rate'], 'correct' => 'A'],
                    ['q' => 'Keeping raw/source data separate from processed/derived data in different folders helps to:', 'options' => ['A' => 'Waste storage space', 'B' => 'Prevent accidental edits and confusion about which version is current', 'C' => 'Slow down GIS software', 'D' => 'Make metadata unnecessary'], 'correct' => 'B'],
                    ['q' => 'Overlay/intersection analysis is used to:', 'options' => ['A' => 'Combine two layers to find where they spatially coincide', 'B' => "Change a raster's colour scheme", 'C' => 'Convert vector data to a spreadsheet only', 'D' => 'Rename files automatically'], 'correct' => 'A'],
                ],
            ],
            2 => [
                'title' => 'Visualization and Mapping Quiz',
                'questions' => [
                    ['q' => 'Sequential colour schemes (light to dark) are best used for data that:', 'options' => ['A' => 'Has a meaningful midpoint like zero', 'B' => 'Goes from low to high with no natural midpoint', 'C' => 'Is categorical with no order', 'D' => 'Should always use red and green together'], 'correct' => 'B'],
                    ['q' => 'When exporting a map for print, a recommended resolution is:', 'options' => ['A' => '72 DPI', 'B' => '300 DPI', 'C' => '10 DPI', 'D' => "Resolution doesn't matter for print"], 'correct' => 'B'],
                    ['q' => 'Why should a published dashboard always show a "last updated" indicator?', 'options' => ['A' => 'Users might otherwise assume outdated data is current', 'B' => 'It has no purpose', 'C' => 'It is a decorative element only', 'D' => 'To hide the data source'], 'correct' => 'A'],
                ],
            ],
        ],
        'gis-and-mapping-in-crime-analysis' => [
            0 => [
                'title' => 'Foundations of Crime Mapping Quiz',
                'questions' => [
                    ['q' => 'Routine activity theory states that crime occurs when:', 'options' => ['A' => 'A motivated offender, suitable target and absence of a capable guardian converge', 'B' => 'Police are simply not present', 'C' => 'Crime is randomly distributed', 'D' => 'Population density is irrelevant'], 'correct' => 'A'],
                    ['q' => 'Geocoding is the process of:', 'options' => ['A' => 'Deleting incident records', 'B' => 'Converting a text address into map coordinates', 'C' => 'Encrypting crime data', 'D' => 'Measuring rainfall'], 'correct' => 'B'],
                    ['q' => 'Why should crime incident-type categories be standardised before analysis?', 'options' => ['A' => 'It has no effect on results', 'B' => 'Inconsistent categories fragment the data and understate concentration', 'C' => 'It changes the map projection', 'D' => 'It is only relevant to satellite imagery'], 'correct' => 'B'],
                ],
            ],
            1 => [
                'title' => 'Hotspot and Trend Analysis Quiz',
                'questions' => [
                    ['q' => 'Kernel Density Estimation (KDE) produces:', 'options' => ['A' => 'A table of raw counts only', 'B' => 'A smooth, continuous density surface from discrete incident points', 'C' => 'A list of suspect names', 'D' => 'A weather forecast'], 'correct' => 'B'],
                    ['q' => 'Why is it important to test whether a crime trend holds under slightly different analysis parameters?', 'options' => ['A' => "It isn't important", 'B' => 'Search radius and unit of analysis can materially change what looks like a hotspot', 'C' => 'Parameters never affect results', 'D' => 'Only one parameter setting is ever valid'], 'correct' => 'B'],
                    ['q' => 'A short time window with a small incident count can produce:', 'options' => ['A' => 'Perfectly reliable percentage trend claims', 'B' => 'Dramatic-looking but statistically noisy percentage changes', 'C' => 'No numerical result at all', 'D' => 'Only qualitative results'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Reporting and Decision Support Quiz',
                'questions' => [
                    ['q' => 'A risk of purely density-driven resource allocation is:', 'options' => ['A' => 'It has no risks', 'B' => 'It can create a feedback loop that reinforces the original allocation', 'C' => 'It always reduces crime instantly', 'D' => 'It eliminates the need for further analysis'], 'correct' => 'B'],
                    ['q' => 'Operational commanders generally need maps that are:', 'options' => ['A' => 'Historical only, with no urgency', 'B' => 'Current, with clear priority ranking for immediate deployment', 'C' => 'Purely decorative', 'D' => 'Focused on privacy law only'], 'correct' => 'B'],
                    ['q' => 'A strong crime-analysis briefing should lead with:', 'options' => ['A' => 'A detailed explanation of the KDE algorithm', 'B' => 'The finding and its implication', 'C' => 'A disclaimer only', 'D' => 'An unrelated topic'], 'correct' => 'B'],
                ],
            ],
        ],
        'introduction-to-gis-using-arcgis-desktop' => [
            0 => [
                'title' => 'Getting Started with ArcGIS Quiz',
                'questions' => [
                    ['q' => 'In ArcGIS Pro, the pane that lists every layer in the current map and controls draw order/visibility is the:', 'options' => ['A' => 'Catalog pane', 'B' => 'Contents pane', 'C' => 'Geoprocessing pane', 'D' => 'Ribbon'], 'correct' => 'B'],
                    ['q' => 'The Catalog pane in ArcGIS Pro functions as:', 'options' => ['A' => 'A file browser connecting to data sources, geodatabases and toolboxes', 'B' => 'A drawing tool', 'C' => 'A printer settings menu', 'D' => 'A user account manager'], 'correct' => 'A'],
                    ['q' => 'ArcGIS Pro organizes work primarily around a:', 'options' => ['A' => 'Single flat file', 'B' => 'Project, containing maps, layouts and data connections', 'C' => 'Spreadsheet', 'D' => 'Web browser tab'], 'correct' => 'B'],
                ],
            ],
            1 => [
                'title' => 'Editing and Managing Data Quiz',
                'questions' => [
                    ['q' => 'A feature class is best described as:', 'options' => ['A' => 'A collection of features sharing the same geometry type and attribute schema', 'B' => 'A type of raster only', 'C' => 'A printed map', 'D' => 'A user permission level'], 'correct' => 'A'],
                    ['q' => 'Snapping during digitising is used to:', 'options' => ['A' => 'Automatically delete features', 'B' => 'Lock the cursor to existing vertices/edges so features connect precisely', 'C' => 'Change the coordinate system', 'D' => 'Compress file size'], 'correct' => 'B'],
                    ['q' => 'Choosing the correct field type (e.g. number vs text) when creating an attribute field matters because:', 'options' => ['A' => 'It has no real effect', 'B' => 'It affects storage and what operations are later possible', 'C' => 'It only affects font colour', 'D' => 'ArcGIS ignores field types'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Analysis and Map Production Quiz',
                'questions' => [
                    ['q' => 'The Clip geoprocessing tool is used to:', 'options' => ['A' => 'Merge two layers with identical attributes', "B" => "Extract the portion of one layer that falls within another layer's boundary", 'C' => "Change a raster's resolution", 'D' => 'Print a map directly'], 'correct' => 'B'],
                    ['q' => 'The Layout view in ArcGIS Pro is used to:', 'options' => ['A' => 'Edit raw feature geometry', 'B' => 'Design a finished, presentation-ready map with title, legend and scale bar', 'C' => 'Import satellite imagery', 'D' => 'Manage user accounts'], 'correct' => 'B'],
                    ['q' => 'A recommended minimum resolution for a map that may be printed is:', 'options' => ['A' => '72 DPI', 'B' => '300 DPI', 'C' => '10 DPI', 'D' => 'There is no minimum'], 'correct' => 'B'],
                ],
            ],
        ],
        'gis-for-health-sector-programme-management' => [
            0 => [
                'title' => 'GIS Foundations for Health Programmes Quiz',
                'questions' => [
                    ['q' => 'Mapping health facility catchment areas primarily helps programme managers understand:', 'options' => ['A' => 'Staff salaries', 'B' => 'Where services are reaching people and where gaps remain', 'C' => 'Marketing strategy', 'D' => 'Weather patterns'], 'correct' => 'B'],
                    ['q' => 'Spatial thinking for programme managers means habitually asking:', 'options' => ['A' => 'How location and distance affect programme outcomes', 'B' => 'What font to use in reports', 'C' => 'How to increase staff headcount only', 'D' => 'None of the above'], 'correct' => 'A'],
                    ['q' => 'Facility catchment mapping is most useful when combined with:', 'options' => ['A' => 'Nothing else', 'B' => 'Population and travel-time data to estimate real accessibility', 'C' => 'Only facility names', 'D' => 'Unrelated data'], 'correct' => 'B'],
                ],
            ],
            1 => [
                'title' => 'Tracking Coverage and Service Delivery Quiz',
                'questions' => [
                    ['q' => 'Identifying "service delivery gaps" spatially typically involves:', 'options' => ['A' => 'Comparing where services are provided against where need/population exists', 'B' => 'Ignoring population data', 'C' => 'Only counting total facilities nationally', 'D' => 'Reviewing staff CVs'], 'correct' => 'A'],
                    ['q' => 'Combining GIS outputs with programme indicators (e.g. immunisation rates) allows managers to:', 'options' => ['A' => 'Replace all statistical analysis', 'B' => 'See where spatial coverage aligns or diverges from performance data', 'C' => 'Avoid needing any reporting', 'D' => 'Eliminate the need for data collection'], 'correct' => 'B'],
                    ['q' => 'A recurring spatial report for programme reviews should ideally be:', 'options' => ['A' => 'Rebuilt entirely by hand every time with no template', 'B' => 'Built around a repeatable process and template', 'C' => 'Produced only once, at project closeout', 'D' => 'Kept secret from stakeholders'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Reporting for Programme Management Quiz',
                'questions' => [
                    ['q' => 'Presenting spatial evidence to donors is most effective when:', 'options' => ['A' => 'The methodology is presented before any finding', 'B' => 'The map is paired with a short, clear narrative explaining what it means', 'C' => 'Maps are shown with no context', 'D' => 'Only raw data tables are shown'], 'correct' => 'B'],
                    ['q' => 'Building recurring spatial reports mainly supports:', 'options' => ['A' => 'One-off analysis with no ongoing value', 'B' => 'Ongoing, evidence-based programme decision-making', 'C' => 'Eliminating the need for M&E staff', 'D' => 'Avoiding donor communication'], 'correct' => 'B'],
                    ['q' => 'Why should a report clearly flag data recency?', 'options' => ['A' => "So decision-makers don't treat outdated data as current", 'B' => 'It has no importance', 'C' => 'To make the report longer', 'D' => 'To hide gaps in the data'], 'correct' => 'A'],
                ],
            ],
        ],
        'gis-and-remote-sensing-for-sustainable-forestry' => [
            0 => [
                'title' => 'Remote Sensing Fundamentals Quiz',
                'questions' => [
                    ['q' => 'Satellite sensors capture reflected light across different wavelength bands, most importantly for forestry:', 'options' => ['A' => 'Visible and near-infrared bands used to assess vegetation health', 'B' => 'Only audio frequencies', 'C' => 'Radio broadcast signals', 'D' => 'None of the above'], 'correct' => 'A'],
                    ['q' => 'A key advantage of free imagery sources like Sentinel-2/Landsat for forestry monitoring is:', 'options' => ['A' => 'They cost nothing and offer resolution suitable for landscape-scale monitoring', 'B' => 'They are only available once per decade', 'C' => 'They cannot be used for vegetation analysis', 'D' => 'They require a satellite dish to access'], 'correct' => 'A'],
                    ['q' => 'When choosing satellite imagery for a project, the two main factors to match to your question are:', 'options' => ['A' => 'Colour and file size', 'B' => 'Resolution and revisit frequency', 'C' => 'Screen resolution and internet speed', 'D' => 'Country and currency'], 'correct' => 'B'],
                ],
            ],
            1 => [
                'title' => 'Forest Monitoring Techniques Quiz',
                'questions' => [
                    ['q' => 'Classifying forest cover from imagery typically relies on:', 'options' => ['A' => 'Manually redrawing the whole image by hand only', 'B' => 'A vegetation index like NDVI and/or supervised classification', 'C' => 'Ignoring the imagery entirely', 'D' => 'Weather forecasts'], 'correct' => 'B'],
                    ['q' => 'A common false signal in forest-change detection is:', 'options' => ['A' => 'True deforestation', 'B' => 'Seasonal variation between wet and dry season imagery mistaken for real change', 'C' => "An unrelated country's data", 'D' => 'A broken printer'], 'correct' => 'B'],
                    ['q' => 'Detecting deforestation reliably requires:', 'options' => ['A' => 'A single image from any one date', 'B' => 'Consistent classification methods and comparable imagery across dates', 'C' => 'No imagery at all', 'D' => 'Only ground survey data'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Planning and Reporting Quiz',
                'questions' => [
                    ['q' => 'Integrating GIS and remote sensing outputs into a forestry report is most valuable when it:', 'options' => ['A' => 'Only shows raw satellite images with no interpretation', 'B' => 'Translates spatial findings into clear figures like hectares lost per year', 'C' => 'Avoids any numerical summary', 'D' => 'Excludes the time dimension'], 'correct' => 'B'],
                    ['q' => 'A forestry monitoring report aimed at programme managers should prioritise:', 'options' => ['A' => 'Total area lost, rate of loss, and zones losing forest fastest', 'B' => 'Only the software version used', 'C' => "The satellite manufacturer's history", 'D' => 'Unrelated case studies'], 'correct' => 'A'],
                    ['q' => 'Why should forestry monitoring workflows be designed to be repeated over time?', 'options' => ['A' => 'Because monitoring is a one-time task', 'B' => 'Because forest change is an ongoing process best tracked at intervals', 'C' => 'It has no benefit', 'D' => 'Because software updates require it'], 'correct' => 'B'],
                ],
            ],
        ],
        'gis-for-disaster-risk-management' => [
            0 => [
                'title' => 'Hazard Mapping Foundations Quiz',
                'questions' => [
                    ['q' => 'A hazard map primarily shows:', 'options' => ['A' => 'Zones exposed to a specific type of hazard, such as flooding', 'B' => 'Only population density', 'C' => 'Administrative boundaries with no hazard data', 'D' => "Tomorrow's weather forecast"], 'correct' => 'A'],
                    ['q' => 'GIS concepts for disaster risk build on:', 'options' => ['A' => 'Layering hazard, exposure and vulnerability data spatially', 'B' => 'Ignoring elevation data', 'C' => 'Using only social media posts', 'D' => 'A single non-spatial spreadsheet'], 'correct' => 'A'],
                    ['q' => 'Mapping hazard zones (e.g. flood-prone areas) commonly relies on:', 'options' => ['A' => 'Elevation data and historical hazard event records', 'B' => 'Random guessing', 'C' => 'Only interviews with no spatial data', 'D' => 'Currency exchange rates'], 'correct' => 'A'],
                ],
            ],
            1 => [
                'title' => 'Vulnerability and Exposure Analysis Quiz',
                'questions' => [
                    ['q' => '"Exposure" in disaster risk analysis refers to:', 'options' => ['A' => 'People, assets or infrastructure located in a hazard zone', 'B' => 'The brightness of a satellite image', 'C' => 'A camera setting', 'D' => "A country's GDP"], 'correct' => 'A'],
                    ['q' => 'Assessing population vulnerability typically combines:', 'options' => ['A' => 'Hazard zones with socioeconomic and demographic data', 'B' => 'Only satellite imagery with no demographic data', 'C' => 'Currency data only', 'D' => 'Building material colour'], 'correct' => 'A'],
                    ['q' => 'Mapping critical infrastructure exposure (e.g. hospitals, roads) helps planners:', 'options' => ['A' => 'Prioritise protection and response for essential services', 'B' => 'Ignore disaster planning entirely', 'C' => 'Reduce map legend clarity', 'D' => 'Avoid using GIS tools'], 'correct' => 'A'],
                ],
            ],
            2 => [
                'title' => 'Risk Mapping and Response Planning Quiz',
                'questions' => [
                    ['q' => 'A composite risk map is typically built by combining:', 'options' => ['A' => 'Hazard, exposure and vulnerability layers into a single score', 'B' => 'Only rainfall data', 'C' => 'A single satellite photo', 'D' => 'Randomly generated numbers'], 'correct' => 'A'],
                    ['q' => 'Risk maps support response planning primarily by:', 'options' => ['A' => 'Identifying where resources and preparedness should be prioritised', 'B' => 'Replacing the need for emergency services', 'C' => 'Predicting exact event dates', 'D' => 'Eliminating uncertainty entirely'], 'correct' => 'A'],
                    ['q' => 'Composite risk scores should be presented to decision-makers with:', 'options' => ['A' => 'No explanation of how they were calculated', 'B' => 'A clear rationale so the logic can be questioned and refined', 'C' => 'Only raw numbers and no map', 'D' => 'Extreme technical jargon'], 'correct' => 'B'],
                ],
            ],
        ],
        'advanced-web-based-mapping-applications-using-open-source-gis-tools' => [
            0 => [
                'title' => 'Web Mapping Foundations Quiz',
                'questions' => [
                    ['q' => 'A web mapping application typically serves spatial data to a browser using:', 'options' => ['A' => 'A printed atlas', 'B' => 'Web-friendly formats and map tile/data services', 'C' => 'Fax machines', 'D' => 'Only Excel files'], 'correct' => 'B'],
                    ['q' => 'Preparing spatial data for web serving often involves:', 'options' => ['A' => 'Increasing file size unnecessarily', 'B' => 'Simplifying geometries and choosing efficient formats for performance', 'C' => 'Deleting all attribute data', 'D' => 'Removing the coordinate system'], 'correct' => 'B'],
                    ['q' => 'A key difference between a desktop GIS project and a web map is:', 'options' => ['A' => 'Desktop GIS is for one user editing; a web map serves many users in a browser', 'B' => 'They are identical in every way', 'C' => 'Web maps cannot show any data', 'D' => 'Desktop GIS cannot use satellite imagery'], 'correct' => 'A'],
                ],
            ],
            1 => [
                'title' => 'Building Interactive Maps Quiz',
                'questions' => [
                    ['q' => 'Open-source web mapping libraries (e.g. Leaflet) are commonly used to:', 'options' => ['A' => 'Render interactive maps with layers, markers and popups in a browser', 'B' => 'Replace the need for any spatial data', 'C' => 'Only work offline', 'D' => 'Print physical maps'], 'correct' => 'A'],
                    ['q' => 'Adding popups and interactivity to a web map primarily improves:', 'options' => ['A' => 'File compression', 'B' => "The user's ability to explore and get details from map features", 'C' => 'Server security', 'D' => 'Print resolution'], 'correct' => 'B'],
                    ['q' => 'Layering multiple data sources on a web map requires managing:', 'options' => ['A' => 'Draw order and layer visibility/styling', 'B' => 'Nothing beyond adding files', 'C' => 'Only text formatting', 'D' => 'Currency conversion'], 'correct' => 'A'],
                ],
            ],
            2 => [
                'title' => 'Deployment and Publishing Quiz',
                'questions' => [
                    ['q' => 'Hosting a web map for public access typically requires:', 'options' => ['A' => 'A web server or hosting platform to serve the map files and data', 'B' => 'No server at all', 'C' => 'A printed copy mailed to users', 'D' => 'A single offline desktop computer'], 'correct' => 'A'],
                    ['q' => 'Performance considerations for a published web map include:', 'options' => ['A' => 'Data size, tile caching and load times', 'B' => "Only the map's colour scheme", 'C' => "The user's shoe size", 'D' => 'None, performance never matters'], 'correct' => 'A'],
                    ['q' => 'Ongoing maintenance of a published web map should include:', 'options' => ['A' => 'Never checking it again after launch', 'B' => 'Monitoring for broken data links and updating source data', 'C' => 'Deleting it after one use', 'D' => 'Ignoring user feedback'], 'correct' => 'B'],
                ],
            ],
        ],
        'mobile-data-collection-using-ona-and-kobo-toolbox' => [
            0 => [
                'title' => 'Digital Survey Design Quiz',
                'questions' => [
                    ['q' => 'Good form design principles include:', 'options' => ['A' => 'Asking as many open-ended questions as possible with no structure', 'B' => 'Clear, unambiguous questions with appropriate skip logic and validation', 'C' => 'Avoiding any instructions to enumerators', 'D' => 'Using only paper forms'], 'correct' => 'B'],
                    ['q' => 'XLSForm is a standard used to:', 'options' => ['A' => 'Define survey forms in a spreadsheet format that tools like Kobo can render', 'B' => 'Compress satellite imagery', 'C' => 'Encrypt survey passwords', 'D' => 'Design print layouts'], 'correct' => 'A'],
                    ['q' => 'Skip logic in a digital form is used to:', 'options' => ['A' => 'Show or hide questions based on previous answers', 'B' => 'Randomly skip validation entirely', 'C' => 'Slow down data entry deliberately', 'D' => 'Change the server location'], 'correct' => 'A'],
                ],
            ],
            1 => [
                'title' => 'Deploying Surveys with Ona and Kobo Quiz',
                'questions' => [
                    ['q' => 'Setting up a project in KoboToolbox typically involves:', 'options' => ['A' => 'Uploading the form and configuring project/user access', 'B' => 'Only using a paper printer', 'C' => 'Deleting the survey after upload', 'D' => 'Ignoring data security settings'], 'correct' => 'A'],
                    ['q' => 'Testing a form on mobile devices before full rollout helps to:', 'options' => ['A' => 'Catch usability issues and errors before wide deployment', 'B' => 'Guarantee zero errors are possible', 'C' => 'Replace the need for training', 'D' => 'Increase form length unnecessarily'], 'correct' => 'A'],
                    ['q' => 'Deploying surveys with Kobo/Ona typically requires enumerators to have:', 'options' => ['A' => 'The Kobo Collect/Ona app installed and access credentials', 'B' => 'No device at all', 'C' => 'A print shop nearby', 'D' => 'A satellite phone only'], 'correct' => 'A'],
                ],
            ],
            2 => [
                'title' => 'Managing and Exporting Field Data Quiz',
                'questions' => [
                    ['q' => 'Monitoring incoming submissions during data collection helps to:', 'options' => ['A' => 'Catch data quality issues early, such as missing or inconsistent responses', 'B' => 'Prevent any data from being collected', 'C' => 'Replace the need for data cleaning entirely', 'D' => 'Automatically fix all errors with no review'], 'correct' => 'A'],
                    ['q' => 'Exporting and cleaning collected data typically involves:', 'options' => ['A' => 'Downloading data and checking for duplicates and inconsistent categories', 'B' => 'Deleting the dataset immediately', 'C' => 'Ignoring data quality checks', 'D' => 'Randomizing the responses'], 'correct' => 'A'],
                    ['q' => 'A benefit of digital data collection tools like Kobo/Ona over paper forms is:', 'options' => ['A' => 'Built-in validation and faster access to submitted data', 'B' => 'They require no internet or device ever', 'C' => 'They cannot include skip logic', 'D' => 'They eliminate the need for form design'], 'correct' => 'A'],
                ],
            ],
        ],
        'mobile-data-collection-using-odk' => [
            0 => [
                'title' => 'Introduction to ODK Quiz',
                'questions' => [
                    ['q' => 'The ODK ecosystem generally includes:', 'options' => ['A' => 'A form-building tool, a mobile collection app, and a server for data', 'B' => 'Only a single mobile app with no server', 'C' => 'A paper-only workflow', 'D' => 'A weather forecasting tool'], 'correct' => 'A'],
                    ['q' => 'Setting up a first ODK project typically involves:', 'options' => ['A' => 'Configuring a server, uploading a form, and connecting the collection app', 'B' => 'Printing paper questionnaires only', 'C' => 'Skipping form design entirely', 'D' => 'Deleting existing forms first'], 'correct' => 'A'],
                    ['q' => 'ODK is primarily used for:', 'options' => ['A' => 'Mobile-based digital data collection in the field', 'B' => 'Editing satellite imagery', 'C' => 'Financial auditing', 'D' => 'Web map hosting'], 'correct' => 'A'],
                ],
            ],
            1 => [
                'title' => 'Form Design in ODK Quiz',
                'questions' => [
                    ['q' => 'Skip logic in ODK form design allows:', 'options' => ['A' => 'Questions to be shown or hidden based on prior answers', 'B' => 'The form to delete itself after use', 'C' => 'The server to change automatically', 'D' => 'None of the above'], 'correct' => 'A'],
                    ['q' => 'Adding validation and constraints to ODK form fields helps to:', 'options' => ['A' => 'Prevent invalid or out-of-range data from being entered', 'B' => 'Make forms slower with no benefit', 'C' => 'Remove the need for skip logic', 'D' => 'Change the map projection'], 'correct' => 'A'],
                    ['q' => 'A well-designed ODK form reduces:', 'options' => ['A' => 'Data entry errors and enumerator confusion in the field', 'B' => 'The need for any testing', 'C' => 'The number of available question types', 'D' => 'Server security'], 'correct' => 'A'],
                ],
            ],
            2 => [
                'title' => 'Field Deployment and Data Management Quiz',
                'questions' => [
                    ['q' => 'Deploying a form to ODK Collect requires:', 'options' => ['A' => 'Enumerators to download/sync the form via the configured server', 'B' => 'No device or app at all', 'C' => 'Manually retyping the form on paper', 'D' => 'Deleting the server'], 'correct' => 'A'],
                    ['q' => 'Quality-checking exported ODK data typically involves:', 'options' => ['A' => 'Reviewing for missing values, duplicates and inconsistent entries', 'B' => 'Ignoring the exported file entirely', 'C' => 'Immediately publishing raw data with no review', 'D' => 'Changing the form questions after collection is finished'], 'correct' => 'A'],
                    ['q' => "A key advantage of ODK's digital forms over paper-based collection is:", 'options' => ['A' => 'Built-in validation and structured, exportable data', 'B' => 'They cannot be used offline', 'C' => 'They require no form design', 'D' => 'They cannot include constraints'], 'correct' => 'A'],
                ],
            ],
        ],
        'research-design-data-management-and-statistical-analysis-using-spss' => [
            0 => [
                'title' => 'Research Design Quiz',
                'questions' => [
                    ['q' => 'Choosing a research methodology should be driven primarily by:', 'options' => ['A' => 'The research question and what evidence is needed to answer it', 'B' => 'Whatever is easiest regardless of the question', 'C' => 'The software available only', 'D' => 'Random selection'], 'correct' => 'A'],
                    ['q' => 'A well-designed sampling strategy aims to:', 'options' => ['A' => 'Produce a sample that reasonably represents the target population', 'B' => 'Always survey 100% of a population regardless of feasibility', 'C' => 'Avoid any consideration of bias', 'D' => 'Ignore the research question'], 'correct' => 'A'],
                    ['q' => 'Questionnaire planning should occur:', 'options' => ['A' => 'After data collection has already started', 'B' => 'Before data collection, aligned with the research objectives', 'C' => 'Only after publishing results', 'D' => 'It is unnecessary'], 'correct' => 'B'],
                ],
            ],
            1 => [
                'title' => 'Data Management in SPSS Quiz',
                'questions' => [
                    ['q' => 'Importing and structuring data in SPSS typically involves:', 'options' => ['A' => 'Defining variable types, labels and value labels for each column', 'B' => 'Deleting all variable names', 'C' => 'Avoiding any variable definitions', 'D' => 'Only importing images'], 'correct' => 'A'],
                    ['q' => 'Data cleaning and recoding in SPSS commonly includes:', 'options' => ['A' => 'Checking for out-of-range values, duplicates, and recoding categories consistently', 'B' => 'Ignoring inconsistent entries', 'C' => 'Deleting the entire dataset', 'D' => 'Publishing data without review'], 'correct' => 'A'],
                    ['q' => 'Why is consistent variable coding (e.g. 1=Yes, 2=No) important across a dataset?', 'options' => ['A' => 'It has no importance', 'B' => 'Inconsistent coding leads to incorrect and misleading results', 'C' => 'It only affects file size', 'D' => 'SPSS ignores variable codes'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Statistical Analysis and Reporting Quiz',
                'questions' => [
                    ['q' => 'Descriptive statistics (mean, median, mode) are used to:', 'options' => ['A' => 'Summarise the basic features of a dataset', 'B' => 'Prove causation between variables', 'C' => 'Replace the need for any data collection', 'D' => 'Encrypt survey data'], 'correct' => 'A'],
                    ['q' => 'Cross-tabulation is useful for:', 'options' => ['A' => 'Examining the relationship between two categorical variables', 'B' => 'Only calculating the mean of one variable', 'C' => "Formatting a report's font", 'D' => 'Deleting outliers automatically'], 'correct' => 'A'],
                    ['q' => 'When reporting statistical findings, it is best practice to:', 'options' => ['A' => 'Report only the results that support a preferred conclusion', 'B' => 'Clearly state the method used and the meaning of results in plain language', 'C' => 'Avoid stating the sample size', 'D' => 'Never mention limitations'], 'correct' => 'B'],
                ],
            ],
        ],
        'advanced-financial-management-grants-management-and-auditing-for-donor-funded-projects' => [
            0 => [
                'title' => 'Donor Financial Management Quiz',
                'questions' => [
                    ['q' => 'Donor financial reporting standards typically require:', 'options' => ["A" => "Reports that follow the donor's specific format, timelines and cost categories", 'B' => 'No reporting until project closeout', 'C' => 'Reports in any format regardless of donor requirements', 'D' => 'Verbal updates only'], 'correct' => 'A'],
                    ['q' => 'Budgeting for a donor-funded project should be:', 'options' => ['A' => 'Aligned with the approved donor budget lines and activities', 'B' => 'Created independently of the approved proposal', 'C' => 'Left until the project has ended', 'D' => 'Based only on guesswork'], 'correct' => 'A'],
                    ['q' => 'A core purpose of donor financial reporting is to:', 'options' => ['A' => 'Demonstrate accountable and compliant use of funds against the agreed budget', 'B' => 'Increase overhead costs', 'C' => 'Avoid transparency', 'D' => 'Replace the need for audits'], 'correct' => 'A'],
                ],
            ],
            1 => [
                'title' => 'Grants Management and Compliance Quiz',
                'questions' => [
                    ['q' => 'A grant budget amendment is typically required when:', 'options' => ['A' => 'Spending needs to shift significantly from the originally approved budget lines', 'B' => 'Nothing has changed', 'C' => 'The project has ended', 'D' => 'A donor visits the office'], 'correct' => 'A'],
                    ['q' => '"Eligible costs" under a donor grant refers to:', 'options' => ['A' => "Expenses that meet the donor's specific rules for what can be charged", 'B' => 'Any expense the organisation chooses', 'C' => 'Only staff salaries', 'D' => 'Costs incurred before the grant started, with no restriction'], 'correct' => 'A'],
                    ['q' => 'Grant compliance is best maintained by:', 'options' => ['A' => 'Reviewing rules only once at the very end of a project', 'B' => 'Ongoing monitoring of spending and activities against donor rules', 'C' => 'Ignoring donor agreements', 'D' => 'Delegating all compliance to the donor'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Auditing and Internal Controls Quiz',
                'questions' => [
                    ['q' => 'Preparing for a donor or statutory audit is most effective when:', 'options' => ['A' => 'Records are kept audit-ready continuously, not assembled at the last minute', 'B' => 'Records are created retroactively just before the audit', 'C' => 'No documentation is kept', 'D' => 'Audits are avoided entirely'], 'correct' => 'A'],
                    ['q' => 'Segregation of duties (e.g. separating who approves payments from who records them) primarily helps to:', 'options' => ['A' => 'Slow down all financial processes with no benefit', 'B' => 'Reduce the risk of error or fraud going undetected', 'C' => 'Increase overhead costs only', 'D' => 'Replace the need for any records'], 'correct' => 'B'],
                    ['q' => 'A strong internal control environment includes:', 'options' => ['A' => 'Clear approval workflows and regular reconciliation of accounts', 'B' => 'A single person handling all financial tasks with no oversight', 'C' => 'No documented procedures', 'D' => 'Ignoring discrepancies as routine'], 'correct' => 'A'],
                ],
            ],
        ],
        'warehouse-and-store-management' => [
            0 => [
                'title' => 'Warehouse Operations Fundamentals Quiz',
                'questions' => [
                    ['q' => 'A well-planned warehouse layout functions as:', 'options' => ['A' => 'Pure decoration with no operational value', 'B' => 'A form of inventory control that reduces errors and safety incidents', 'C' => 'A legal requirement with no other benefit', 'D' => 'Something irrelevant to stock accuracy'], 'correct' => 'B'],
                    ['q' => 'Every incoming delivery should be checked against its documentation:', 'options' => ['A' => 'Only if the supplier requests it', 'B' => 'Before being accepted into inventory, to catch discrepancies early', 'C' => 'After it has already been shelved and forgotten', 'D' => 'Never, to save time'], 'correct' => 'B'],
                    ['q' => 'Clear location-coding (zone, aisle, shelf, bin) in a warehouse primarily helps to:', 'options' => ['A' => 'Ensure any item can be found without relying on memory', 'B' => 'Increase confusion intentionally', 'C' => 'Replace the need for stock records', 'D' => 'Reduce available storage space'], 'correct' => 'A'],
                ],
            ],
            1 => [
                'title' => 'Inventory and Stock Control Quiz',
                'questions' => [
                    ['q' => 'A stock card records:', 'options' => ['A' => 'Every receipt, dispatch and adjustment for an item, with a running balance', 'B' => "Only the item's supplier name", 'C' => 'Marketing information', 'D' => 'Employee attendance'], 'correct' => 'A'],
                    ['q' => 'FIFO (First In, First Out) is especially important for:', 'options' => ['A' => 'Perishable or expiry-dated items', 'B' => 'Non-perishable items only', 'C' => 'Office supplies exclusively', 'D' => 'It has no real application'], 'correct' => 'A'],
                    ['q' => 'When a physical stock count reveals a discrepancy from recorded balances, the correct response is to:', 'options' => ['A' => 'Silently adjust the record with no investigation', 'B' => 'Investigate the cause rather than just adjusting it away', 'C' => "Ignore it if it's small", 'D' => 'Delete the stock card'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Compliance and Reporting Quiz',
                'questions' => [
                    ['q' => 'A warehouse audit typically checks whether:', 'options' => ['A' => 'Physical stock matches recorded balances and safety standards are followed', 'B' => 'Only the warehouse paint colour is correct', 'C' => 'Staff have decorated their desks', 'D' => 'The warehouse is empty'], 'correct' => 'A'],
                    ['q' => 'The best way to stay audit-ready is to:', 'options' => ['A' => 'Maintain accurate, current records continuously', 'B' => 'Prepare only in the days before an announced audit', 'C' => 'Avoid keeping any documentation', 'D' => 'Rely on memory instead of records'], 'correct' => 'A'],
                    ['q' => 'A good periodic stock report should prominently flag:', 'options' => ['A' => 'Items approaching stockout or expiry, and unresolved discrepancies', 'B' => 'Only items with no issues', 'C' => 'Irrelevant historical data from years ago', 'D' => 'Nothing in particular'], 'correct' => 'A'],
                ],
            ],
        ],
        'cambridge-igcse-mathematics' => [
            0 => [
                'title' => 'Number and Algebra Foundations Quiz',
                'questions' => [
                    ['q' => 'What is 15% of 240?', 'options' => ['A' => '24', 'B' => '36', 'C' => '30', 'D' => '40'], 'correct' => 'B'],
                    ['q' => 'Which of these correctly expresses 3,400,000 in standard form?', 'options' => ['A' => '3.4 × 10^5', 'B' => '3.4 × 10^6', 'C' => '34 × 10^5', 'D' => '0.34 × 10^7'], 'correct' => 'B'],
                    ['q' => 'Factorise x^2 + 5x + 6.', 'options' => ['A' => '(x+2)(x+3)', 'B' => '(x+1)(x+6)', 'C' => '(x-2)(x-3)', 'D' => '(x+6)(x-1)'], 'correct' => 'A'],
                ],
            ],
            1 => [
                'title' => 'Geometry, Mensuration and Trigonometry Quiz',
                'questions' => [
                    ['q' => 'The interior angles of a triangle always sum to:', 'options' => ['A' => '90°', 'B' => '180°', 'C' => '270°', 'D' => '360°'], 'correct' => 'B'],
                    ['q' => "In a right-angled triangle, Pythagoras' theorem states that:", 'options' => ['A' => 'a + b = c', 'B' => 'a^2 + b^2 = c^2, where c is the hypotenuse', 'C' => 'a^2 - b^2 = c', 'D' => 'a × b = c^2'], 'correct' => 'B'],
                    ['q' => 'The area of a circle with radius r is given by:', 'options' => ['A' => '2πr', 'B' => 'πr^2', 'C' => 'πd', 'D' => '2πr^2'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Graphs, Statistics and Probability Quiz',
                'questions' => [
                    ['q' => "In the equation y = mx + c, what does 'm' represent?", 'options' => ['A' => 'The y-intercept', 'B' => 'The gradient of the line', 'C' => 'The x-intercept', 'D' => 'A constant with no meaning'], 'correct' => 'B'],
                    ['q' => 'If two events are mutually exclusive, the probability of either occurring is found using:', 'options' => ['A' => 'Multiplication of their probabilities', 'B' => 'Addition of their probabilities', 'C' => 'Division of their probabilities', 'D' => 'Subtracting one from the other'], 'correct' => 'B'],
                    ['q' => 'A cumulative frequency diagram is primarily used to find:', 'options' => ['A' => 'The mode only', 'B' => 'The median and quartiles of grouped data', 'C' => 'The exact individual data values', 'D' => 'The name of the dataset'], 'correct' => 'B'],
                ],
            ],
        ],
        'cambridge-igcse-physics' => [
            0 => [
                'title' => 'Motion, Forces and Energy Quiz',
                'questions' => [
                    ['q' => 'On a speed-time graph, the gradient represents:', 'options' => ['A' => 'Distance', 'B' => 'Acceleration', 'C' => 'Time', 'D' => 'Mass'], 'correct' => 'B'],
                    ["q" => "Newton's second law is expressed as:", 'options' => ['A' => 'F = ma', 'B' => 'E = mc^2', 'C' => 'V = IR', 'D' => 'P = IV'], 'correct' => 'A'],
                    ['q' => 'Kinetic energy is calculated using:', 'options' => ['A' => 'mgh', 'B' => '½mv^2', 'C' => 'Fd', 'D' => 'IV'], 'correct' => 'B'],
                ],
            ],
            1 => [
                'title' => 'Thermal Physics and Waves Quiz',
                'questions' => [
                    ['q' => 'During a change of state, while energy continues to be supplied, temperature:', 'options' => ['A' => 'Rises steadily', 'B' => 'Stays constant', 'C' => 'Falls', 'D' => 'Becomes negative'], 'correct' => 'B'],
                    ['q' => 'Which method of heat transfer can occur through a vacuum?', 'options' => ['A' => 'Conduction', 'B' => 'Convection', 'C' => 'Radiation', 'D' => 'None of these'], 'correct' => 'C'],
                    ['q' => 'The relationship between wave speed, frequency and wavelength is:', 'options' => ['A' => 'speed = frequency × wavelength', 'B' => 'speed = frequency ÷ wavelength', 'C' => 'speed = frequency + wavelength', 'D' => 'speed = wavelength ÷ frequency'], 'correct' => 'A'],
                ],
            ],
            2 => [
                'title' => 'Electricity, Magnetism and Atomic Physics Quiz',
                'questions' => [
                    ["q" => "Ohm's law is expressed as:", 'options' => ['A' => 'V = IR', 'B' => 'F = ma', 'C' => 'P = mgh', 'D' => 'E = mc^2'], 'correct' => 'A'],
                    ['q' => 'In a series circuit, current is:', 'options' => ['A' => 'The same at every point in the circuit', 'B' => 'Different in each component', 'C' => 'Always zero', 'D' => 'Only present in the first component'], 'correct' => 'A'],
                    ['q' => 'Which type of radioactive emission is stopped by just a sheet of paper?', 'options' => ['A' => 'Alpha particles', 'B' => 'Beta particles', 'C' => 'Gamma rays', 'D' => 'All of the above'], 'correct' => 'A'],
                ],
            ],
        ],
        'cambridge-igcse-biology' => [
            0 => [
                'title' => 'Cell Biology and Life Processes Quiz',
                'questions' => [
                    ['q' => 'Which structure is found in plant cells but not animal cells?', 'options' => ['A' => 'Nucleus', 'B' => 'Cell wall', 'C' => 'Cytoplasm', 'D' => 'Cell membrane'], 'correct' => 'B'],
                    ['q' => 'Osmosis is best described as:', 'options' => ['A' => 'The movement of any particle against a concentration gradient using energy', 'B' => 'The movement of water across a partially permeable membrane', 'C' => 'The digestion of proteins', 'D' => 'A type of active transport'], 'correct' => 'B'],
                    ['q' => 'Which enzyme breaks down carbohydrates?', 'options' => ['A' => 'Protease', 'B' => 'Lipase', 'C' => 'Amylase', 'D' => 'Insulin'], 'correct' => 'C'],
                ],
            ],
            1 => [
                'title' => 'Human Systems and Reproduction Quiz',
                'questions' => [
                    ['q' => 'The human circulatory system is described as a "double" circulatory system because:', 'options' => ['A' => 'It has two hearts', 'B' => 'Blood passes through the heart twice in one full circuit', 'C' => 'It only serves two organs', 'D' => 'It has no clear structure'], 'correct' => 'B'],
                    ['q' => 'Anaerobic respiration in humans produces:', 'options' => ['A' => 'Carbon dioxide and water', 'B' => 'Lactic acid', 'C' => 'Oxygen', 'D' => 'Glucose'], 'correct' => 'B'],
                    ['q' => 'A reflex arc allows a fast response because:', 'options' => ['A' => 'It requires conscious thought first', 'B' => 'It follows a fixed, short pathway that bypasses conscious brain involvement', 'C' => 'It only works during sleep', 'D' => 'It bypasses the nervous system entirely'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Genetics, Evolution and Ecology Quiz',
                'questions' => [
                    ['q' => 'An allele that appears in the phenotype only when no dominant allele is present is called:', 'options' => ['A' => 'Dominant', 'B' => 'Recessive', 'C' => 'Codominant', 'D' => 'Mutated'], 'correct' => 'B'],
                    ['q' => 'In a food chain, energy decreases at each successive trophic level mainly because:', 'options' => ['A' => 'Energy is lost as heat through respiration and other processes', 'B' => 'Energy is created at each level', 'C' => 'Producers absorb all the energy', 'D' => 'Energy cannot be transferred between organisms'], 'correct' => 'A'],
                    ['q' => 'Decomposers play a key role in nutrient cycles by:', 'options' => ['A' => 'Producing oxygen only', 'B' => 'Breaking down dead organic matter to release nutrients', 'C' => 'Consuming only living plants', 'D' => 'Blocking the carbon cycle'], 'correct' => 'B'],
                ],
            ],
        ],
        'cambridge-igcse-business-studies' => [
            0 => [
                'title' => 'Business Activity and Organisation Quiz',
                'questions' => [
                    ['q' => 'The factors of production include land, labour, capital and:', 'options' => ['A' => 'Enterprise', 'B' => 'Inflation', 'C' => 'Marketing', 'D' => 'Currency'], 'correct' => 'A'],
                    ['q' => 'A sole trader is characterised by:', 'options' => ['A' => 'Limited liability and shared ownership', 'B' => 'Unlimited liability and single ownership', 'C' => 'Shareholders and a board of directors', 'D' => 'Government ownership'], 'correct' => 'B'],
                    ['q' => 'An organisation\'s "span of control" refers to:', 'options' => ['A' => 'The geographic area a business operates in', 'B' => 'How many people a manager directly supervises', 'C' => "The company's total revenue", 'D' => 'The number of products sold'], 'correct' => 'B'],
                ],
            ],
            1 => [
                'title' => 'People and Marketing Quiz',
                'questions' => [
                    ['q' => 'Financial motivators for employees include:', 'options' => ['A' => 'Job enrichment and promotion opportunities only', 'B' => 'Wages, bonuses and profit-sharing', 'C' => 'Improved working conditions only', 'D' => 'None of the above'], 'correct' => 'B'],
                    ['q' => 'The "four Ps" of the marketing mix are:', 'options' => ['A' => 'Product, Price, Place, Promotion', 'B' => 'People, Process, Physical evidence, Product', 'C' => 'Profit, Price, Product, People', 'D' => 'Planning, Pricing, Placement, Performance'], 'correct' => 'A'],
                    ['q' => 'Penetration pricing involves:', 'options' => ['A' => 'Setting a high initial price for a new product', 'B' => 'Setting a low initial price to gain market share', 'C' => "Matching competitors' prices exactly", 'D' => 'Randomly changing prices daily'], 'correct' => 'B'],
                ],
            ],
            2 => [
                'title' => 'Finance and External Influences Quiz',
                'questions' => [
                    ['q' => 'A business can be profitable on paper yet still fail because of:', 'options' => ['A' => 'Poor cash flow timing', 'B' => 'Too much profit', 'C' => 'Having no competitors', 'D' => 'Too many customers'], 'correct' => 'A'],
                    ['q' => 'A cash flow forecast is used to:', 'options' => ['A' => 'Calculate total annual profit only', 'B' => 'Identify future cash shortages before they happen', 'C' => 'Replace the need for a statement of financial position', 'D' => 'Set employee salaries'], 'correct' => 'B'],
                    ['q' => 'Higher interest rates typically:', 'options' => ['A' => 'Reduce the cost of borrowing', 'B' => 'Increase the cost of borrowing and can reduce consumer spending', 'C' => 'Have no effect on businesses', 'D' => 'Only affect government spending'], 'correct' => 'B'],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->quizzes as $courseSlug => $modules) {
            $course = Course::where('slug', $courseSlug)->first();

            if (!$course) {
                continue;
            }

            foreach ($modules as $orderIndex => $quizData) {
                $module = CourseModule::where('course_id', $course->id)
                    ->where('order_index', $orderIndex)
                    ->first();

                if (!$module) {
                    continue;
                }

                $quiz = ModuleQuiz::updateOrCreate(
                    ['module_id' => $module->id],
                    [
                        'title' => $quizData['title'],
                        'instructions' => 'Answer every question. You need at least 70% correct to pass and unlock the next module.',
                        'passing_percent' => 70,
                        'max_attempts' => 3,
                        'cooldown_hours' => 0,
                    ]
                );

                foreach ($quizData['questions'] as $index => $question) {
                    ModuleQuizQuestion::updateOrCreate(
                        [
                            'quiz_id' => $quiz->id,
                            'order_index' => $index,
                        ],
                        [
                            'question_text' => $question['q'],
                            'options' => $question['options'],
                            'correct_option_key' => $question['correct'],
                        ]
                    );
                }
            }
        }
    }
}
