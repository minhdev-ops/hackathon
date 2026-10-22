import { useParams } from 'react-router-dom';
import ConsentReview from '../components/grant/ConsentReview';

export default function ConsentReviewPage() {
    const { grantId } = useParams();

    return <ConsentReview grantId={grantId} />;
}
