# Enhanced Export and Navigation Features

## New Features Implemented

### 1. Previous Question Navigation
- Added `api/previous_question.php` endpoint to handle going back to the previous question
- Updated `host_session.php` to include a "Question précédente" button that:
  - Is disabled on the first question
  - Is enabled on all other questions
  - Allows hosts to navigate backwards through questions

### 2. Enhanced Export Functionality
- Created `api/export_enhanced.php` with advanced export features:
  - **ASCII Charts in PDF**: Generated visual charts using ASCII art for better data visualization
  - **Individual Question Files**: Each question gets its own CSV file with only responses for that question
  - **Organized Directory Structure**: Creates a folder with all export files organized
  - **README File**: Includes documentation explaining the export contents

### 3. Export Features Verification
- **All Questions Included**: Verified that existing export already includes all questions (not limited to 8)
- **Feedback Questions**: Confirmed that feedback/comment questions are properly exported
- **Multiple Question Types**: All question types (quiz, truefalse, short, long, rating, feedback) are handled

## Files Modified
- `host_session.php`: Added previous question button and enhanced export option
- `.gitignore`: Added database and export files to exclusion list

## Files Added
- `api/previous_question.php`: Previous question navigation endpoint
- `api/export_enhanced.php`: Enhanced export with charts and individual files

## Export Options Now Available
1. **CSV Export**: Basic CSV export of all responses
2. **XLS Export**: Excel-compatible export
3. **Complete Folder (PDF + CSV)**: Original export with basic PDF and CSV
4. **Advanced Export (Charts + Individual Files)**: New enhanced export with:
   - PDF with ASCII charts
   - Individual CSV file per question
   - Combined responses CSV
   - README documentation
   - Organized in a folder structure

## Usage
- Hosts can now navigate forward and backward through questions during live sessions
- Enhanced export provides detailed analysis with visual charts and granular per-question data
- All export formats include feedback questions and comments from participants